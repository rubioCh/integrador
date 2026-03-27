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

class HubspotContactResponseSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_hubspot_contact_using_inverse_mapping_from_destination_response(): void
    {
        config()->set('hubspot.access_token', 'token_123');
        config()->set('hubspot.base_url', 'https://api.hubapi.test');

        Http::fake([
            'https://api.hubapi.test/crm/v3/objects/contacts/401' => Http::response([
                'id' => '401',
                'properties' => [
                    'firstname' => 'Ana',
                    'email' => 'ana@example.com',
                ],
            ], 200),
        ]);

        $hubspotPlatform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'credentials' => ['access_token' => 'token_123'],
            'active' => true,
        ]);

        $aspelPlatform = Platform::query()->create([
            'name' => 'ASPEL',
            'slug' => 'aspel',
            'type' => 'generic',
            'active' => true,
        ]);

        $mappingEvent = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Contacto cambio de etapa',
            'event_type_id' => 'contact.propertyChange',
            'type' => 'webhook',
            'active' => true,
        ]);

        $writebackEvent = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Actualizar contacto HubSpot con respuesta destino',
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

        $hubspotFirstname = Property::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Nombre',
            'key' => 'firstname',
            'type' => 'string',
            'active' => true,
        ]);
        $hubspotEmail = Property::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Correo',
            'key' => 'email',
            'type' => 'string',
            'active' => true,
        ]);
        $aspelNombre = Property::query()->create([
            'platform_id' => $aspelPlatform->id,
            'name' => 'Nombre',
            'key' => 'nombre',
            'type' => 'string',
            'active' => true,
        ]);
        $aspelEmail = Property::query()->create([
            'platform_id' => $aspelPlatform->id,
            'name' => 'Correo envio',
            'key' => 'emailEnvio',
            'type' => 'string',
            'active' => true,
        ]);

        PropertyRelationship::query()->create([
            'event_id' => $mappingEvent->id,
            'property_id' => $hubspotFirstname->id,
            'related_property_id' => $aspelNombre->id,
            'active' => true,
        ]);
        PropertyRelationship::query()->create([
            'event_id' => $mappingEvent->id,
            'property_id' => $hubspotEmail->id,
            'related_property_id' => $aspelEmail->id,
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
                    'id' => 'asp_999',
                    'nombre' => 'Ana',
                    'emailEnvio' => 'ana@example.com',
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('401', $result['data']['contact_id']);
        $this->assertSame('Ana', $result['data']['updated_properties']['firstname']);
        $this->assertSame('ana@example.com', $result['data']['updated_properties']['email']);

        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'PATCH' || $request->url() !== 'https://api.hubapi.test/crm/v3/objects/contacts/401') {
                return false;
            }

            $properties = $request->data()['properties'] ?? [];

            return ($properties['firstname'] ?? null) === 'Ana'
                && ($properties['email'] ?? null) === 'ana@example.com';
        });
    }
}
