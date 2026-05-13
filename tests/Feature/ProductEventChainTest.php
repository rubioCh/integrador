<?php

namespace Tests\Feature;

use App\Jobs\ProcessNextEventJob;
use App\Jobs\ProcessProductCreationJob;
use App\Jobs\ProcessProductUpdateJob;
use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;
use App\Services\Hubspot\HubspotApiServiceRefactored;
use App\Services\Hubspot\ProductCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ProductEventChainTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_product_update_job_dispatches_creation_event_with_not_found_payload(): void
    {
        Queue::fake();

        $platform = Platform::query()->create([
            'name' => 'Hubspot corripio',
            'slug' => 'hubspot-corripio',
            'type' => 'hubspot',
            'credentials' => [
                'access_token' => 'token_123',
            ],
            'active' => true,
        ]);

        $createEvent = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Creación de productos',
            'event_type_id' => 'product.created',
            'type' => 'webhook',
            'active' => true,
        ]);

        $updateEvent = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Actualización de productos',
            'event_type_id' => 'product.updated',
            'type' => 'webhook',
            'to_event_id' => $createEvent->id,
            'active' => true,
        ]);

        $record = Record::query()->create([
            'event_id' => $updateEvent->id,
            'event_type' => 'product.updated',
            'status' => 'init',
            'payload' => [
                [
                    'identificador_db' => 'MC-000077777',
                    'name' => 'Producto a crear',
                ],
            ],
            'message' => 'init',
        ]);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('products', 'identificador_db', 'MC-000077777', ['identificador_db'])
            ->andReturn([
                'success' => true,
                'data' => ['results' => []],
            ]);
        $hubspotApi->shouldNotReceive('updateProduct');

        $productCache = Mockery::mock(ProductCacheService::class);
        $productCache->shouldNotReceive('preload');

        $this->app->instance(HubspotApiServiceRefactored::class, $hubspotApi);
        $this->app->instance(ProductCacheService::class, $productCache);

        $job = new ProcessProductUpdateJob($updateEvent->fresh('to_event'), $record, [
            [
                'identificador_db' => 'MC-000077777',
                'name' => 'Producto a crear',
            ],
        ]);
        $job->handle(app(\App\Services\EventProcessingService::class), app(\App\Services\EventLoggingService::class));

        Queue::assertPushed(ProcessNextEventJob::class, function (ProcessNextEventJob $job) use ($updateEvent): bool {
            return $job->event->id === $updateEvent->id
                && ($job->data[0]['identificador_db'] ?? null) === 'MC-000077777';
        });

        $record->refresh();
        $this->assertSame('MC-000077777', data_get($record->details, 'output_payload.0.identificador_db'));
    }

    public function test_product_creation_job_persists_output_payload_even_without_next_event(): void
    {
        $platform = Platform::query()->create([
            'name' => 'Hubspot corripio',
            'slug' => 'hubspot-corripio',
            'type' => 'hubspot',
            'credentials' => [
                'access_token' => 'token_123',
            ],
            'active' => true,
        ]);

        $createEvent = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Creación de productos',
            'event_type_id' => 'product.created',
            'type' => 'webhook',
            'active' => true,
        ]);

        $record = Record::query()->create([
            'event_id' => $createEvent->id,
            'event_type' => 'product.created',
            'status' => 'init',
            'payload' => [
                [
                    'identificador_db' => 'MC-000066666',
                    'name' => 'Producto creado',
                ],
            ],
            'message' => 'init',
        ]);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('createProduct')
            ->once()
            ->with([
                'identificador_db' => 'MC-000066666',
                'name' => 'Producto creado',
            ])
            ->andReturn([
                'success' => true,
                'data' => [
                    'id' => 'prd-666',
                ],
            ]);

        $productCache = Mockery::mock(ProductCacheService::class);
        $productCache->shouldReceive('preload')->once();

        $this->app->instance(HubspotApiServiceRefactored::class, $hubspotApi);
        $this->app->instance(ProductCacheService::class, $productCache);

        $job = new ProcessProductCreationJob($createEvent, $record, [
            [
                'identificador_db' => 'MC-000066666',
                'name' => 'Producto creado',
            ],
        ]);
        $job->handle(app(\App\Services\EventProcessingService::class), app(\App\Services\EventLoggingService::class));

        $record->refresh();
        $this->assertSame('success', $record->status);
        $this->assertSame('MC-000066666', data_get($record->details, 'output_payload.0.identificador_db'));
        $this->assertSame('prd-666', data_get($record->details, 'output_payload.0.hubspot_id'));
    }
}
