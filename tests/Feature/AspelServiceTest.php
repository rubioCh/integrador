<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventHttpConfig;
use App\Models\Platform;
use App\Services\Aspel\AspelService;
use App\Services\EventProcessingService;
use App\Services\Generic\AuthStrategyResolver;
use App\Services\Generic\GenericHttpAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AspelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_processing_service_resolves_aspel_service_for_generic_aspel_platform(): void
    {
        $platform = Platform::query()->create([
            'name' => 'ASPEL Fertifarma',
            'slug' => 'aspel-fertifarma',
            'type' => 'generic',
            'active' => true,
        ]);

        $serviceClass = app(EventProcessingService::class)->getServiceClass($platform);

        $this->assertSame(AspelService::class, $serviceClass);
    }

    public function test_it_excludes_technical_sync_fields_from_aspel_request_body(): void
    {
        $platform = Platform::query()->create([
            'name' => 'ASPEL Fertifarma',
            'slug' => 'aspel-fertifarma',
            'type' => 'generic',
            'credentials' => [
                'api_key' => 'token_123',
            ],
            'settings' => [
                'service_driver' => 'aspel',
            ],
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Sync Contact ASPEL',
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

        $service = new AspelService(
            $platform,
            app(AuthStrategyResolver::class),
            $event,
            null,
        );

        $body = $service->resolveBody($event, [
            'nombre' => 'Demo Integrador',
            'rfc' => 'RFC123',
            'clave' => '50900',
            'sync_to_aspel' => 'pending',
            'sync_status_aspel' => 'success',
            'last_sync_aspel' => '2026-04-07T10:00:00Z',
            'last_error_aspel' => 'none',
            'hubspot_object_id' => '208589143093',
            'destination_response' => ['data' => ['clave' => '50900']],
            'source_event_id' => 5,
        ]);

        $this->assertSame([
            'nombre' => 'Demo Integrador',
            'rfc' => 'RFC123',
            'clave' => '50900',
        ], $body);
    }

    public function test_it_executes_upsert_contact_against_explicit_upsert_endpoint(): void
    {
        $platform = Platform::query()->create([
            'name' => 'ASPEL Fertifarma',
            'slug' => 'aspel-fertifarma',
            'type' => 'generic',
            'credentials' => [
                'api_key' => 'token_123',
            ],
            'settings' => [
                'service_driver' => 'aspel',
            ],
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Upsert Contact ASPEL',
            'event_type_id' => 'generic.external.call',
            'method_name' => 'upsertContact',
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

        $service = new AspelService(
            $platform,
            app(AuthStrategyResolver::class),
            $event,
            null,
        );

        $adapter = Mockery::mock(GenericHttpAdapter::class);
        $adapter->shouldReceive('send')->once()->withArgs(function (
            string $platformKey,
            string $endpoint,
            string $method,
            array $headers,
            array $query,
            array $body
        ): bool {
            return $platformKey === 'aspel'
                && $endpoint === 'https://api.example.com/contacts/upsert'
                && $method === 'POST'
                && ($body['nombre'] ?? null) === 'Demo Integrador'
                && ! array_key_exists('sync_to_aspel', $body);
        })->andReturn([
            'success' => true,
            'status_code' => 201,
            'retryable' => false,
            'request_id' => 'req_aspel_1',
            'external_id' => '50900',
            'latency_ms' => 10,
            'attempt' => 1,
            'endpoint' => 'https://api.example.com/contacts/upsert',
            'method' => 'POST',
            'data' => ['clave' => '50900'],
            'error' => [
                'code' => null,
                'message' => null,
                'details' => null,
            ],
        ]);

        $response = $service->executeEndpointCall([
            'nombre' => 'Demo Integrador',
            'sync_to_aspel' => 'pending',
        ], $adapter);

        $this->assertTrue($response['success']);
        $this->assertSame('50900', $response['external_id']);
    }

    public function test_it_executes_update_contact_with_put_and_clave_path(): void
    {
        $platform = Platform::query()->create([
            'name' => 'ASPEL Fertifarma',
            'slug' => 'aspel-fertifarma',
            'type' => 'generic',
            'credentials' => [
                'api_key' => 'token_123',
            ],
            'settings' => [
                'service_driver' => 'aspel',
            ],
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Update Contact ASPEL',
            'event_type_id' => 'generic.external.call',
            'method_name' => 'updateContact',
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

        $service = new AspelService(
            $platform,
            app(AuthStrategyResolver::class),
            $event,
            null,
        );

        $adapter = Mockery::mock(GenericHttpAdapter::class);
        $adapter->shouldReceive('send')->once()->withArgs(function (
            string $platformKey,
            string $endpoint,
            string $method,
            array $headers,
            array $query,
            array $body
        ): bool {
            return $platformKey === 'aspel'
                && $endpoint === 'https://api.example.com/contacts/50902'
                && $method === 'PUT'
                && ($body['clave'] ?? null) === '50902'
                && ($body['telefono'] ?? null) === '5550001111';
        })->andReturn([
            'success' => true,
            'status_code' => 200,
            'retryable' => false,
            'request_id' => 'req_aspel_2',
            'external_id' => '50902',
            'latency_ms' => 12,
            'attempt' => 1,
            'endpoint' => 'https://api.example.com/contacts/50902',
            'method' => 'PUT',
            'data' => [
                'success' => true,
                'operation' => 'updated',
                'clave' => '50902',
                'message' => 'Contact updated successfully',
            ],
            'error' => [
                'code' => null,
                'message' => null,
                'details' => null,
            ],
        ]);

        $response = $service->executeEndpointCall([
            'clave' => '50902',
            'telefono' => '5550001111',
            'sync_to_aspel' => 'pending',
        ], $adapter);

        $this->assertTrue($response['success']);
        $this->assertSame('PUT', $response['method']);
    }

    public function test_it_supports_polling_updated_contacts_with_explicit_get_operation(): void
    {
        $platform = Platform::query()->create([
            'name' => 'ASPEL Fertifarma',
            'slug' => 'aspel-fertifarma',
            'type' => 'generic',
            'credentials' => [
                'api_key' => 'token_123',
            ],
            'settings' => [
                'service_driver' => 'aspel',
            ],
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Poll Updated Contacts ASPEL',
            'event_type_id' => 'generic.external.call',
            'method_name' => 'getUpdatedContacts',
            'type' => 'schedule',
            'active' => true,
        ]);

        EventHttpConfig::query()->create([
            'event_id' => $event->id,
            'method' => 'GET',
            'base_url' => 'https://api.example.com',
            'path' => '/contacts/updated',
            'active' => true,
        ]);

        $service = new AspelService(
            $platform,
            app(AuthStrategyResolver::class),
            $event,
            null,
        );

        $adapter = Mockery::mock(GenericHttpAdapter::class);
        $adapter->shouldReceive('send')->once()->withArgs(function (
            string $platformKey,
            string $endpoint,
            string $method
        ): bool {
            return $platformKey === 'aspel'
                && $endpoint === 'https://api.example.com/contacts/updated'
                && $method === 'GET';
        })->andReturn([
            'success' => true,
            'status_code' => 200,
            'retryable' => false,
            'request_id' => 'req_poll_1',
            'external_id' => null,
            'latency_ms' => 8,
            'attempt' => 1,
            'endpoint' => 'https://api.example.com/contacts/updated',
            'method' => 'GET',
            'data' => [
                ['clave' => '50900', 'nombre' => 'Demo Integrador'],
            ],
            'error' => [
                'code' => null,
                'message' => null,
                'details' => null,
            ],
        ]);

        $response = $service->executeEndpointCall([
            'updated_since' => '2026-04-07T00:00:00Z',
        ], $adapter);

        $this->assertTrue($response['success']);
        $this->assertSame('GET', $response['method']);
    }
}
