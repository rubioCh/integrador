<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;
use App\Services\Hubspot\HubspotFilePropertyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EventProcessingService
{
    public function __construct(
        protected EventTriggerService $eventTriggerService,
        protected EventLoggingService $eventLoggingService,
        protected HubspotFilePropertyService $hubspotFilePropertyService
    ) {
    }

    public function processEvent(string $subscriptionType, array $payload, Platform $platform, ?Record $parentRecord = null): array
    {
        $parentRecord = $parentRecord ?? $this->eventLoggingService->createEventRecord(
            'webhook_init',
            'init',
            $payload,
            'Webhook processing initiated'
        );

        $events = Event::query()
            ->where('platform_id', $platform->id)
            ->where('subscription_type', $subscriptionType)
            ->where('active', true)
            ->get();

        $events = $events->merge(
            Event::query()
                ->where('platform_id', $platform->id)
                ->where('event_type_id', $subscriptionType)
                ->where('active', true)
                ->get()
        )->unique('id')->values();

        if ($events->isEmpty()) {
            $events = Event::query()
                ->where('platform_id', $platform->id)
                ->where('name', $subscriptionType)
                ->where('active', true)
                ->get();
        }

        if ($events->isEmpty()) {
            $this->eventLoggingService->createEventRecord(
                'webhook_warning',
                'warning',
                $payload,
                'No events found for subscription type',
                $parentRecord->id
            );

            return [
                'success' => false,
                'message' => 'No events found for subscription type',
                'record' => $parentRecord,
                'processed_events' => [],
            ];
        }

        $processedEvents = [];

        foreach ($events as $event) {
            if (! $this->eventTriggerService->evaluateEventTriggers($event, $payload)) {
                $processedEvents[] = [
                    'event_id' => $event->id,
                    'event_name' => $event->name,
                    'skipped' => true,
                    'success' => true,
                    'message' => 'Trigger conditions not met',
                ];
                continue;
            }

            $processedEvents[] = $this->executeEvent($event, $payload, $platform, $parentRecord);
        }

        return [
            'success' => true,
            'record' => $parentRecord,
            'processed_events' => $processedEvents,
            'total_events' => count($processedEvents),
        ];
    }

    public function executeEvent(Event $event, array $payload, Platform $platform, ?Record $parentRecord = null): array
    {
        if ($platform->type === 'hubspot') {
            $payload = $this->hubspotFilePropertyService->hydrateFileProperties($event, $payload);
        }

        $record = $this->eventLoggingService->createEventRecord(
            $event->event_type_id ?? $event->name,
            'init',
            $payload,
            'Event dispatched',
            $parentRecord?->id,
            $event->id
        );

        $eventClass = $this->resolveEventClass($event);

        if (! $eventClass || ! class_exists($eventClass)) {
            $record->update([
                'status' => 'warning',
                'message' => 'Event class not found for dispatch',
                'details' => [
                    'reason' => 'event_class_not_found',
                    'event_id' => $event->id,
                    'event_type_id' => $event->event_type_id,
                    'event_class' => $eventClass,
                ],
            ]);

            Log::warning('Event class not found', [
                'event_id' => $event->id,
                'event_type_id' => $event->event_type_id,
                'event_class' => $eventClass,
            ]);

            return [
                'event_id' => $event->id,
                'event_name' => $event->name,
                'record_id' => $record->id,
                'success' => false,
                'error' => 'Event class not found',
            ];
        }

        event(new $eventClass($event, $record, $payload));

        $record->update([
            'status' => 'processing',
            'message' => 'Event dispatched to listener',
        ]);

        return [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'record_id' => $record->id,
            'success' => true,
        ];
    }

    public function getServiceClass(Platform $platform): ?string
    {
        $platformDriverClassList = app()->bound('platformDriverClassList') ? app('platformDriverClassList') : [];
        $driverKey = $this->resolvePlatformDriverKey($platform);

        if ($driverKey !== null && isset($platformDriverClassList[$driverKey])) {
            return $platformDriverClassList[$driverKey];
        }

        $platformClassList = app()->bound('platformClassList') ? app('platformClassList') : [];

        return $platformClassList[$platform->type] ?? null;
    }

    private function resolvePlatformDriverKey(Platform $platform): ?string
    {
        $settings = is_array($platform->settings) ? $platform->settings : [];
        $credentials = is_array($platform->credentials) ? $platform->credentials : [];

        foreach ([
            $settings['service_driver'] ?? null,
            $settings['integration_driver'] ?? null,
            $credentials['service_driver'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return Str::lower(trim($candidate));
            }
        }

        $slug = Str::lower(trim((string) $platform->slug));
        $name = Str::lower(trim((string) $platform->name));

        if ($platform->type === 'generic' && (Str::startsWith($slug, 'aspel') || Str::contains($name, 'aspel'))) {
            return 'aspel';
        }

        return null;
    }

    public function dispatchEvent(Event $event, Record $record, array $data): void
    {
        $eventClass = $this->resolveEventClass($event);
        if ($eventClass && class_exists($eventClass)) {
            event(new $eventClass($event, $record, $data));
            return;
        }

        Log::warning('Event class not found for dispatch', [
            'event_id' => $event->id,
            'event_type_id' => $event->event_type_id,
            'event_class' => $eventClass,
        ]);
    }

    private function resolveEventClass(Event $event): ?string
    {
        return $event->getEventClass();
    }
}
