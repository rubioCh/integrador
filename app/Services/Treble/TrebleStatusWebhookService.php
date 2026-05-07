<?php

namespace App\Services\Treble;

use App\Models\Client;
use App\Models\PlatformConnection;
use App\Models\Record;
use App\Services\EventLoggingService;
use Illuminate\Support\Arr;

class TrebleStatusWebhookService
{
    public function __construct(
        protected EventLoggingService $eventLoggingService
    ) {
    }

    public function process(Client $client, PlatformConnection $connection, array $payload): array
    {
        $eventId = trim((string) Arr::get($payload, 'event_id', ''));
        $eventType = trim((string) Arr::get($payload, 'event_type', ''));
        $externalId = trim((string) Arr::get($payload, 'session.external_id', ''));
        $timestamp = trim((string) Arr::get($payload, 'timestamp', ''));
        $closedAt = trim((string) Arr::get($payload, 'session.closed_at', ''));
        $phone = $this->normalizePhone((string) Arr::get($payload, 'user.cellphone', ''));
        $hsmName = trim((string) Arr::get($payload, 'hsm.name', ''));

        if ($eventId === '' || $eventType === '') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Treble callback missing required event fields.',
            ];
        }

        $record = $this->findMatchingRecord($client->id, $externalId, $phone, $hsmName);

        if (! $record) {
            $warning = $this->eventLoggingService->createEventRecord(
                'treble.status.unmatched',
                'warning',
                $payload,
                'Treble status callback could not be matched to an existing record.',
                null,
                null,
                $client->id
            );

            $warning->update([
                'details' => [
                    'reason' => 'unmatched_external_id',
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'external_id' => $externalId !== '' ? $externalId : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'template_name' => $hsmName !== '' ? $hsmName : null,
                ],
            ]);

            return [
                'success' => true,
                'status_code' => 200,
                'matched' => false,
            ];
        }

        $details = is_array($record->details) ? $record->details : [];
        $trebleStatus = is_array($details['treble_status'] ?? null) ? $details['treble_status'] : [];
        $history = is_array($trebleStatus['history'] ?? null) ? $trebleStatus['history'] : [];

        foreach ($history as $entry) {
            if (($entry['event_id'] ?? null) === $eventId) {
                return [
                    'success' => true,
                    'status_code' => 200,
                    'matched' => true,
                    'duplicate' => true,
                    'record_id' => $record->id,
                ];
            }
        }

        $history[] = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'timestamp' => $timestamp !== '' ? $timestamp : null,
            'external_id' => $externalId !== '' ? $externalId : null,
            'closed_at' => $closedAt !== '' ? $closedAt : null,
            'payload' => $payload,
        ];

        $details['treble_status'] = [
            'current' => $eventType,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'external_id' => $externalId !== '' ? $externalId : ($trebleStatus['external_id'] ?? null),
            'closed_at' => $closedAt !== '' ? $closedAt : ($trebleStatus['closed_at'] ?? null),
            'last_payload' => $payload,
            'updated_at' => now()->toISOString(),
            'history' => $history,
        ];

        $record->update([
            'message' => 'Treble status updated: ' . $eventType,
            'details' => $details,
        ]);

        return [
            'success' => true,
            'status_code' => 200,
            'matched' => true,
            'record_id' => $record->id,
        ];
    }

    private function findMatchingRecord(int $clientId, string $externalId, string $phone, string $templateName): ?Record
    {
        if ($externalId !== '') {
            $matched = Record::query()
                ->where('client_id', $clientId)
                ->where(function ($query) use ($externalId): void {
                    $query->where('details->treble_status->external_id', $externalId)
                        ->orWhere('details->treble_response->external_id', $externalId);
                })
                ->latest('id')
                ->first();

            if ($matched) {
                return $matched;
            }
        }

        if ($phone === '') {
            return null;
        }

        return Record::query()
            ->where('client_id', $clientId)
            ->where(function ($query) use ($phone): void {
                $query->where('details->treble_request->phone', $phone)
                    ->orWhere('details->contact_properties->phone', $phone)
                    ->orWhere('details->contact_properties->mobilephone', $phone);
            })
            ->when($templateName !== '', function ($query) use ($templateName): void {
                $query->where(function ($inner) use ($templateName): void {
                    $inner->where('details->treble_request->template_name', $templateName)
                        ->orWhere('details->matched_rule_name', $templateName);
                });
            })
            ->latest('id')
            ->first();
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
