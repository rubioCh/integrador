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

class HubspotContactFailureNoteTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_structured_failure_note_for_writeback_errors(): void
    {
        config()->set('hubspot.access_token', 'token_123');
        config()->set('hubspot.base_url', 'https://api.hubapi.test');
        config()->set('hubspot.note_association_type_ids.contacts', 202);

        Http::fake([
            'https://api.hubapi.test/crm/v3/objects/contacts/401' => Http::response([
                'status' => 'error',
                'message' => 'Property values were not valid',
                'category' => 'VALIDATION_ERROR',
                'errors' => [[
                    'message' => 'hs_object_id es una propiedad de solo lectura.',
                    'code' => 'READ_ONLY_VALUE',
                    'context' => [
                        'propertyName' => ['hs_object_id'],
                    ],
                ]],
            ], 400),
            'https://api.hubapi.test/crm/v3/objects/notes' => Http::response([
                'id' => '9901',
            ], 201),
            'https://api.hubapi.test/crm/v3/objects/notes/9901/associations/contact/401/202' => Http::response([], 204),
            'https://api.hubapi.test/crm/v3/objects/contact/401/associations/notes/9901/201' => Http::response([], 204),
        ]);

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
            'name' => 'HubSpot Object Id',
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
            'hubspot_object_id' => '401',
            'destination_execution' => [
                'source_event_id' => $mappingEvent->id,
            ],
            'destination_response' => [
                'data' => [
                    'hs_object_id' => '401',
                ],
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertTrue((bool) ($result['data']['hubspot_note']['success'] ?? false));

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.hubapi.test/crm/v3/objects/notes') {
                return false;
            }

            $body = $request->data()['properties']['hs_note_body'] ?? '';

            return str_contains($body, '[Integrador] Error de sincronizacion de contacto')
                && str_contains($body, 'Operacion: write-back a HubSpot')
                && str_contains($body, 'Propiedad: hs_object_id')
                && str_contains($body, 'Codigo: READ_ONLY_VALUE')
                && str_contains($body, 'Categoria: VALIDATION_ERROR');
        });
    }
}
