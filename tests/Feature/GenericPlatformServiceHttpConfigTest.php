<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventHttpConfig;
use App\Models\Platform;
use App\Services\Generic\AuthStrategyResolver;
use App\Services\Generic\GenericPlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenericPlatformServiceHttpConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_http_configuration_from_event_http_configs_table(): void
    {
        $platform = Platform::query()->create([
            'name' => 'Generic',
            'slug' => 'generic',
            'type' => 'generic',
            'settings' => [
                'headers' => ['X-Platform' => 'integrador'],
            ],
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'External Call',
            'event_type_id' => 'generic.external.call',
            'method_name' => 'POST',
            'endpoint_api' => '/legacy/path',
            'type' => 'webhook',
            'active' => true,
            'payload_mapping' => [
                'payload.deal_id' => 'deal.id',
            ],
        ]);

        EventHttpConfig::query()->create([
            'event_id' => $event->id,
            'method' => 'PATCH',
            'base_url' => 'https://api.example.com',
            'path' => '/v1/orders/sync',
            'headers_json' => ['X-Tenant' => 'tenant_1'],
            'query_json' => ['source' => 'integrador-v2'],
            'timeout_seconds' => 45,
            'retry_policy_json' => ['max_attempts' => 5],
            'idempotency_config_json' => ['enabled' => true, 'ttl_hours' => 12],
            'active' => true,
        ]);

        $service = new GenericPlatformService(
            $platform,
            $event,
            null,
            app(AuthStrategyResolver::class),
        );

        $endpoint = $service->resolveEndpoint($event);
        $method = $service->resolveMethod($event);
        $query = $service->resolveQueryParams($event, ['query' => ['foo' => 'bar']]);
        $body = $service->resolveBody($event, ['payload' => ['deal_id' => 99]]);
        $timeout = $service->resolveTimeout($event);
        $retryPolicy = $service->resolveRetryPolicy($event);
        $idempotency = $service->resolveIdempotencyPolicy($event);

        $this->assertSame('https://api.example.com/v1/orders/sync', $endpoint);
        $this->assertSame('PATCH', $method);
        $this->assertSame([
            'source' => 'integrador-v2',
            'foo' => 'bar',
        ], $query);
        $this->assertSame([
            'deal' => ['id' => 99],
        ], $body);
        $this->assertSame(45, $timeout);
        $this->assertSame(5, $retryPolicy['max_attempts']);
        $this->assertTrue($idempotency['enabled']);
        $this->assertSame(12, $idempotency['ttl_hours']);
    }
}
