<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Platform;
use App\Services\Hubspot\HubspotApiServiceRefactored;
use App\Services\Hubspot\HubspotService;
use App\Services\Hubspot\ProductCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HubspotProductUpdateResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_product_updated_event_can_resolve_hubspot_id_by_identificador_db_before_updating(): void
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

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Actualización de productos',
            'event_type_id' => 'product.updated',
            'type' => 'webhook',
            'active' => true,
        ]);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('products', 'identificador_db', 'MC-000037512', ['identificador_db'])
            ->andReturn([
                'success' => true,
                'data' => [
                    'results' => [
                        ['id' => '2001'],
                    ],
                ],
            ]);
        $hubspotApi->shouldReceive('updateProduct')
            ->once()
            ->with('2001', [
                'identificador_db' => 'MC-000037512',
                'name' => 'Producto Corripio',
            ])
            ->andReturn([
                'success' => true,
                'data' => ['id' => '2001'],
            ]);

        $productCache = Mockery::mock(ProductCacheService::class);
        $productCache->shouldReceive('preload')->once();

        $service = new HubspotService($platform, $event, null, $hubspotApi, $productCache);
        $result = $service->updateProducts([
            [
                'identificador_db' => 'MC-000037512',
                'name' => 'Producto Corripio',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(1, data_get($result, 'data.updated_count'));
        $this->assertSame([], data_get($result, 'data.output_payload'));
    }

    public function test_product_updated_event_prepares_not_found_products_for_creation_when_next_event_exists(): void
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

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Actualización de productos',
            'event_type_id' => 'product.updated',
            'type' => 'webhook',
            'to_event_id' => $createEvent->id,
            'active' => true,
        ]);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('products', 'identificador_db', 'MC-000099999', ['identificador_db'])
            ->andReturn([
                'success' => true,
                'data' => [
                    'results' => [],
                ],
            ]);
        $hubspotApi->shouldNotReceive('updateProduct');

        $productCache = Mockery::mock(ProductCacheService::class);
        $productCache->shouldNotReceive('preload');

        $service = new HubspotService($platform, $event, null, $hubspotApi, $productCache);
        $result = $service->updateProducts([
            [
                'identificador_db' => 'MC-000099999',
                'name' => 'Producto nuevo',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertNull($result['status'] ?? null);
        $this->assertSame(0, data_get($result, 'data.updated_count'));
        $this->assertSame(1, data_get($result, 'data.not_found_count'));
        $this->assertSame('MC-000099999', data_get($result, 'data.output_payload.0.identificador_db'));
    }

    public function test_product_updated_event_warns_when_creation_fallback_event_is_missing(): void
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

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Actualización de productos',
            'event_type_id' => 'product.updated',
            'type' => 'webhook',
            'active' => true,
        ]);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('products', 'identificador_db', 'MC-000088888', ['identificador_db'])
            ->andReturn([
                'success' => true,
                'data' => [
                    'results' => [],
                ],
            ]);
        $hubspotApi->shouldNotReceive('updateProduct');

        $productCache = Mockery::mock(ProductCacheService::class);
        $productCache->shouldNotReceive('preload');

        $service = new HubspotService($platform, $event, null, $hubspotApi, $productCache);
        $result = $service->updateProducts([
            [
                'identificador_db' => 'MC-000088888',
                'name' => 'Producto sin fallback',
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('warning', $result['status'] ?? null);
        $this->assertSame('missing_create_fallback_event', data_get($result, 'data.warning_reason'));
    }
}
