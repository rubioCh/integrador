<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;
use App\Services\Hubspot\HubspotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubspotContactResponseSyncMissingContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_clear_context_error_when_hubspot_contact_id_is_missing(): void
    {
        $hubspotPlatform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'credentials' => ['access_token' => 'token_123'],
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Writeback',
            'event_type_id' => 'object.updated',
            'method_name' => 'syncContactExecutionResponse',
            'type' => 'webhook',
            'meta' => [
                'object_type' => 'contacts',
                'response_mapping_event_id' => 1,
            ],
            'active' => true,
        ]);

        $record = Record::query()->create([
            'event_id' => $event->id,
            'event_type' => 'object.updated',
            'status' => 'init',
            'payload' => [],
            'message' => 'init',
        ]);

        $service = app()->make(HubspotService::class, [
            'platform' => $hubspotPlatform,
            'event' => $event,
            'record' => $record,
        ]);

        $result = $service->syncContactExecutionResponse([
            'destination_response' => [
                'data' => [
                    'clave' => '50898',
                ],
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Missing HubSpot contact id for response write-back.', $result['message']);
        $this->assertContains('hubspot_object_id', $result['data']['required_context']);
        $this->assertSame(['destination_response'], $result['data']['received_keys']);
        $this->assertSame(1, $result['data']['mapping_event_id']);
    }
}
