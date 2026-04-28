<?php

namespace App\Jobs\HubSpot;

use App\Models\Client;
use App\Models\PlatformConnection;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\Hubspot\HubspotContactSnapshotService;
use App\Services\Lite\ClientPlatformConfigResolver;
use App\Services\Lite\MessageRuleResolver;
use App\Services\Trebel\TrebelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessContactPropertyChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public Client $client,
        public PlatformConnection $hubspotConnection,
        public array $payload
    ) {
    }

    public function handle(
        EventLoggingService $eventLoggingService,
        HubspotContactSnapshotService $hubspotContactSnapshotService,
        MessageRuleResolver $messageRuleResolver,
        ClientPlatformConfigResolver $configResolver,
        TrebelService $trebelService
    ): void {
        $record = $eventLoggingService->createEventRecord(
            'contact.propertyChange',
            'init',
            $this->payload,
            'HubSpot contact property change received.',
            null,
            null,
            $this->client->id
        );

        $subscriptionType = (string) ($this->payload['subscriptionType'] ?? $this->payload['subscription_type'] ?? '');
        $triggerProperty = (string) ($this->payload['propertyName'] ?? $this->payload['property_name'] ?? '');
        $triggerValue = $this->payload['propertyValue'] ?? $this->payload['property_value'] ?? null;
        $hubspotObjectId = (string) ($this->payload['objectId'] ?? $this->payload['object_id'] ?? '');

        if (strtolower($subscriptionType) !== 'contact.propertychange') {
            $eventLoggingService->logEventWarning($record, 'Unsupported HubSpot subscription type.', [
                'reason' => 'unsupported_subscription_type',
                'subscription_type' => $subscriptionType,
            ]);
            return;
        }

        if ($triggerProperty === '' || $hubspotObjectId === '') {
            $eventLoggingService->logEventWarning($record, 'Missing trigger property or HubSpot object id.', [
                'reason' => 'missing_context',
                'subscription_type' => $subscriptionType,
                'hubspot_object_id' => $hubspotObjectId,
            ]);
            return;
        }

        $requiredProperties = $this->resolveRequiredProperties();
        $contactResponse = $hubspotContactSnapshotService->fetchContact($this->client->id, $hubspotObjectId, $requiredProperties);

        if (! ($contactResponse['success'] ?? false)) {
            $record->update([
                'status' => 'error',
                'message' => $contactResponse['message'] ?? 'HubSpot snapshot failed.',
                'details' => [
                    'client_id' => $this->client->id,
                    'hubspot_object_id' => $hubspotObjectId,
                    'trigger_property' => $triggerProperty,
                    'trigger_value' => $triggerValue,
                    'hubspot_error' => $contactResponse['error'] ?? null,
                ],
            ]);
            return;
        }

        $contact = $contactResponse['data'] ?? [];
        $contactProperties = $contact['properties'] ?? [];
        $rule = $messageRuleResolver->resolve($this->client->id, $contactProperties, $triggerProperty, $triggerValue);

        if (! $rule || ! $rule->trebelTemplate || ! $rule->trebelTemplate->active) {
            $eventLoggingService->logEventWarning($record, 'No active message rule matched this contact.', [
                'client_id' => $this->client->id,
                'hubspot_object_id' => $hubspotObjectId,
                'trigger_property' => $triggerProperty,
                'trigger_value' => $triggerValue,
                'contact_properties' => $contactProperties,
            ]);
            return;
        }

        try {
            $trebelConnection = $configResolver->forClientAndPlatform($this->client->id, 'trebel');
            $trebelResponse = $trebelService->sendTemplate($trebelConnection, $rule->trebelTemplate, $contactProperties, [
                'hubspot_object_id' => $hubspotObjectId,
                'trigger_property' => $triggerProperty,
                'trigger_value' => $triggerValue,
                'contact' => $contact,
            ]);
        } catch (Throwable $exception) {
            $trebelResponse = [
                'success' => false,
                'status_code' => 0,
                'retryable' => false,
                'request_id' => null,
                'external_id' => null,
                'data' => [],
                'error' => [
                    'code' => 'configuration_error',
                    'message' => $exception->getMessage(),
                    'details' => ['exception' => get_class($exception)],
                ],
            ];
        }

        $details = [
            'client_id' => $this->client->id,
            'hubspot_object_id' => $hubspotObjectId,
            'trigger_property' => $triggerProperty,
            'trigger_value' => $triggerValue,
            'matched_rule_id' => $rule->id,
            'matched_rule_name' => $rule->name,
            'trebel_template_id' => $rule->trebelTemplate->external_template_id,
            'trebel_response' => $trebelResponse,
            'contact_properties' => $contactProperties,
            'hubspot_note' => null,
        ];

        if ($trebelResponse['success'] ?? false) {
            $record->update([
                'status' => 'success',
                'message' => 'Trebel template dispatched successfully.',
                'details' => $details,
            ]);
            return;
        }

        $hubspotNote = $hubspotContactSnapshotService->addContactNote(
            $this->hubspotConnection,
            $hubspotObjectId,
            'Trebel dispatch failed for this contact.',
            [
                'rule' => $rule->name,
                'template_id' => $rule->trebelTemplate->external_template_id,
                'status_code' => $trebelResponse['status_code'] ?? null,
                'error' => $trebelResponse['error']['message'] ?? null,
            ]
        );
        $details['hubspot_note'] = $hubspotNote;

        $record->update([
            'status' => 'error',
            'message' => $trebelResponse['error']['message'] ?? 'Trebel dispatch failed.',
            'details' => $details,
        ]);
    }

    private function resolveRequiredProperties(): array
    {
        $defaults = [
            'firstname',
            'lastname',
            'phone',
            'mobilephone',
            'campus_de_interes',
            'nivel_escolar_de_interes',
            'plantilla_de_whatsapp',
        ];

        $configured = $this->hubspotConnection->settings['contact_properties'] ?? [];
        if (! is_array($configured) || $configured === []) {
            return $defaults;
        }

        return array_values(array_unique(array_merge($defaults, $configured)));
    }
}
