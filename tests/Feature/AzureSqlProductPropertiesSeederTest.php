<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\Property;
use Database\Seeders\AzureSqlProductPropertiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AzureSqlProductPropertiesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_azure_sql_product_properties_with_hubspot_mapping_metadata(): void
    {
        $this->seed(AzureSqlProductPropertiesSeeder::class);

        $platform = Platform::query()->where('slug', 'azure-sql')->first();

        $this->assertNotNull($platform);
        $this->assertSame('generic', $platform->type);
        $this->assertSame('azure_sql', data_get($platform->settings, 'service_driver'));

        $this->assertSame(12, Property::query()->where('platform_id', $platform->id)->count());

        $hubspotPlatform = Platform::query()->where('slug', 'hubspot')->first();
        $this->assertNotNull($hubspotPlatform);
        $this->assertSame('hubspot', $hubspotPlatform->type);
        $this->assertSame(12, Property::query()->where('platform_id', $hubspotPlatform->id)->count());

        $identifier = Property::query()
            ->where('platform_id', $platform->id)
            ->where('key', 'itemid')
            ->first();

        $this->assertNotNull($identifier);
        $this->assertSame('Identificador DB', data_get($identifier->meta, 'hubspot_label'));
        $this->assertSame('identificador_db', data_get($identifier->meta, 'hubspot_key'));
        $this->assertSame('inventtable', data_get($identifier->meta, 'table'));

        $hubspotIdentifier = Property::query()
            ->where('platform_id', $hubspotPlatform->id)
            ->where('key', 'identificador_db')
            ->first();

        $this->assertNotNull($hubspotIdentifier);
        $this->assertSame('Identificador DB', $hubspotIdentifier->name);
        $this->assertSame('products', data_get($hubspotIdentifier->meta, 'object_type'));
        $this->assertSame('itemid', data_get($hubspotIdentifier->meta, 'source_db_key'));
    }

    public function test_it_is_idempotent_when_run_multiple_times(): void
    {
        $this->seed(AzureSqlProductPropertiesSeeder::class);
        $this->seed(AzureSqlProductPropertiesSeeder::class);

        $platform = Platform::query()->where('slug', 'azure-sql')->firstOrFail();
        $hubspotPlatform = Platform::query()->where('slug', 'hubspot')->firstOrFail();

        $this->assertSame(12, Property::query()->where('platform_id', $platform->id)->count());
        $this->assertSame(12, Property::query()->where('platform_id', $hubspotPlatform->id)->count());
        $this->assertSame(1, Platform::query()->where('slug', 'azure-sql')->count());
        $this->assertSame(1, Platform::query()->where('slug', 'hubspot')->count());
    }
}
