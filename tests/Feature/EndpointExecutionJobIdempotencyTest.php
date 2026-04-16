<?php

namespace Tests\Feature;

use App\Jobs\Generic\EndpointExecutionJob;
use App\Models\Event;
use App\Models\EventHttpConfig;
use App\Models\EventIdempotencyKey;
use App\Models\Platform;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\EventProcessingService;
use App\Services\Generic\GenericHttpAdapter;
use App\Services\RateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EndpointExecutionJobIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_persistent_idempotency_keys_to_skip_duplicate_execution(): void
    {
        $platform = Platform::query()->create([
            'name' => 'Generic',
            'slug' => 'generic',
            'type' => 'generic',
            'credentials' => [
                'api_key' => 'token_123',
            ],
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'External Call',
            'event_type_id' => 'generic.external.call',
            'type' => 'webhook',
            'active' => true,
        ]);

        EventHttpConfig::query()->create([
            'event_id' => $event->id,
            'method' => 'POST',
            'base_url' => 'https://api.example.com',
            'path' => '/v1/sync',
            'idempotency_config_json' => [
                'enabled' => true,
                'ttl_hours' => 24,
                'key_template' => '{event_id}:{record_id}:{method}:{path}',
            ],
            'active' => true,
        ]);

        $record = Record::query()->create([
            'event_id' => $event->id,
            'event_type' => 'generic.external.call',
            'status' => 'init',
            'payload' => ['payload' => ['id' => 1]],
            'message' => 'init',
        ]);

        $eventProcessingService = app(EventProcessingService::class);
        $eventLoggingService = app(EventLoggingService::class);
        $rateLimitService = app(RateLimitService::class);

        $response = [
            'success' => true,
            'status_code' => 200,
            'retryable' => false,
            'request_id' => 'req_123',
            'external_id' => 'ext_456',
            'latency_ms' => 100,
            'attempt' => 1,
            'endpoint' => 'https://api.example.com/v1/sync',
            'method' => 'POST',
            'data' => ['ok' => true],
            'error' => [
                'code' => null,
                'message' => null,
                'details' => null,
            ],
        ];

        $httpAdapter = Mockery::mock(GenericHttpAdapter::class);
        $httpAdapter->shouldReceive('send')->once()->andReturn($response);

        $job = new EndpointExecutionJob($event, $record, ['payload' => ['id' => 1]]);
        $job->handle($eventProcessingService, $httpAdapter, $eventLoggingService, $rateLimitService);

        $this->assertDatabaseCount('event_idempotency_keys', 1);
        $this->assertDatabaseHas('event_idempotency_keys', [
            'event_id' => $event->id,
            'record_id' => $record->id,
            'status' => 'success',
        ]);

        $secondAdapter = Mockery::mock(GenericHttpAdapter::class);
        $secondAdapter->shouldNotReceive('send');

        $secondJob = new EndpointExecutionJob($event, $record, ['payload' => ['id' => 1]]);
        $secondJob->handle($eventProcessingService, $secondAdapter, $eventLoggingService, $rateLimitService);

        $record->refresh();
        $idempotency = EventIdempotencyKey::query()->first();

        $this->assertSame('warning', $record->status);
        $this->assertSame('success', $idempotency?->status);
    }
}
