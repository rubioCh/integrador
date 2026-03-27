<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Property;
use App\Models\PropertyRelationship;
use App\Models\Record;
use App\Services\Hubspot\HubspotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubspotContactResponseSyncReadonlyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_use_context_only_values_for_writeback_mapping(): void
    {
        config()->set('hubspot.access_token', 'token_123');
        config()->set('hubspot.base_url', 'https://api.hubapi.test');

        Http::fake();

        $hubspotPlatform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'credentials' => ['access_token' => 'token_123'],
            'active' => true,
        ]);

        $genericPlatform = Platform::query()->create([
            'name' => 'Generic',
            'slug' => 'generic',
            'type' => 'generic',
            'active' => true,
        ]);

        $mappingEvent = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Mapping Event',
            'event_type_id' => 'contact.propertyChange',
            'type' => 'webhook',
            'active' => true,
        ]);

        $writebackEvent = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Writeback',
            'event_type_id' => 'object.updated',
            'method_name' => 'syncContactExecutionResponse',
            'type' => 'webhook',
            'meta' => [
                'object_type' => 'contacts',
                'response_mapping_event_id' => $mappingEvent->id,
            ],
            'active' => true,
        ]);

        $record = Record::query()->create([
            'event_id' => $writebackEvent->id,
            'event_type' => 'object.updated',
            'status' => 'init',
            'payload' => [],
            'message' => 'init',
        ]);

        $hubspotReadonly = Property::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'HubSpot Object Id',
            'key' => 'hs_object_id',
            'type' => 'string',
            'active' => true,
        ]);
        $genericReadonly = Property::query()->create([
            'platform_id' => $genericPlatform->id,
            'name' => 'Clave',
            'key' => 'hs_object_id',
            'type' => 'string',
            'active' => true,
        ]);

        PropertyRelationship::query()->create([
            'event_id' => $mappingEvent->id,
            'property_id' => $hubspotReadonly->id,
            'related_property_id' => $genericReadonly->id,
            'active' => true,
        ]);

        $service = app()->make(HubspotService::class, [
            'platform' => $hubspotPlatform,
            'event' => $writebackEvent,
            'record' => $record,
        ]);

        $result = $service->syncContactExecutionResponse([
            'hubspot_object_id' => '208589143093',
            'hs_object_id' => '208589143093',
            'destination_execution' => [
                'source_event_id' => $mappingEvent->id,
            ],
            'destination_response' => [
                'data' => [
                    'clave' => '50898',
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['data']['updated_properties']);
        $this->assertSame(['clave'], $result['data']['destination_response_keys']);
        Http::assertNothingSent();
    }
}
