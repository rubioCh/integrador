<?php

namespace Tests\Feature;

use App\Jobs\Generic\EndpointExecutionJob;
use App\Models\Event;
use App\Models\EventHttpConfig;
use App\Models\Platform;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\Generic\GenericHttpAdapter;
use App\Services\Generic\GenericPlatformService;
use App\Services\Hubspot\HubspotApiServiceRefactored;
use App\Services\RateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class EndpointExecutionJobHubspotFailureNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_hubspot_note_when_generic_contact_sync_fails(): void
    {
        Http::fake([
            'https://api.hubapi.test/crm/v3/objects/notes' => Http::response([
                'id' => '5001',
            ], 201),
            'https://api.hubapi.test/crm/v3/objects/notes/5001/associations/contact/208589143093/202' => Http::response([], 204),
            'https://api.hubapi.test/crm/v3/objects/contact/208589143093/associations/notes/5001/201' => Http::response([], 204),
        ]);

        $hubspotPlatform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'credentials' => ['access_token' => 'token_123'],
            'settings' => ['base_url' => 'https://api.hubapi.test'],
            'active' => true,
        ]);

        $genericPlatform = Platform::query()->create([
            'name' => 'ASPEL',
            'slug' => 'aspel',
            'type' => 'generic',
            'credentials' => ['api_key' => 'token_aspel'],
            'active' => true,
        ]);

        $sourceEvent = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Contacto cambio de etapa',
            'event_type_id' => 'contact.propertyChange',
            'type' => 'webhook',
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $genericPlatform->id,
            'name' => 'Crear Contacto',
            'event_type_id' => 'generic.external.call',
            'type' => 'webhook',
            'active' => true,
        ]);

        EventHttpConfig::query()->create([
            'event_id' => $event->id,
            'method' => 'POST',
            'base_url' => 'https://api.example.com',
            'path' => '/contacts',
            'active' => true,
        ]);

        $record = Record::query()->create([
            'event_id' => $event->id,
            'event_type' => 'generic.external.call',
            'status' => 'init',
            'payload' => [],
            'message' => 'init',
        ]);

        $response = [
            'success' => false,
            'status_code' => 409,
            'retryable' => false,
            'request_id' => '',
            'external_id' => null,
            'latency_ms' => 123,
            'attempt' => 1,
            'endpoint' => 'https://api.example.com/contacts',
            'method' => 'POST',
            'data' => [
                'error' => "Ya existe un contacto con RFC 'LOMJ8502142GR'.",
                'field' => 'rfc',
            ],
            'error' => [
                'code' => null,
                'message' => null,
                'details' => null,
            ],
        ];

        $genericPlatformService = app()->make(GenericPlatformService::class, [
            'platform' => $genericPlatform,
            'event' => $event,
            'record' => $record,
        ]);

        $httpAdapter = Mockery::mock(GenericHttpAdapter::class);
        $httpAdapter->shouldReceive('send')->once()->andReturn($response);

        $job = new EndpointExecutionJob($event->fresh('platform'), $record, [
            'source_event_id' => $sourceEvent->id,
            'hubspot_object_id' => '208589143093',
            'objectId' => 208589143093,
        ]);

        $job->handle(
            $genericPlatformService,
            $httpAdapter,
            app(EventLoggingService::class),
            app(RateLimitService::class),
            app(HubspotApiServiceRefactored::class)
        );

        $record->refresh();

        $this->assertSame('error', $record->status);
        $this->assertTrue((bool) data_get($record->details, 'hubspot_note.success'));
        $this->assertSame('5001', data_get($record->details, 'hubspot_note.note_id'));

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.hubapi.test/crm/v3/objects/notes') {
                return false;
            }

            $body = $request->data()['properties']['hs_note_body'] ?? '';

            return str_contains($body, '[Integrador] Error de sincronizacion de contacto')
                && str_contains($body, 'Operacion: envio a plataforma destino')
                && str_contains($body, "Ya existe un contacto con RFC")
                && str_contains($body, 'Propiedad: rfc');
        });
    }
}
