<?php

namespace Tests\Feature;

use App\Jobs\ExecuteEventJob;
use App\Jobs\ProcessNextEventJob;
use App\Models\Event;
use App\Models\Platform;
use App\Models\Property;
use App\Models\PropertyRelationship;
use App\Services\AzureSql\AzureSqlService;
use App\Services\EventLoggingService;
use App\Services\EventProcessingService;
use App\Services\Hubspot\HubspotApiServiceRefactored;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AzureSqlServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCTS_DEFAULT_QUERY = 'SELECT * FROM [dbo].[inventtable] WHERE [modifieddatetime] >= DATEADD(MINUTE, -24, GETDATE()) ORDER BY [modifieddatetime] ASC';
    private const PRODUCTS_48_HOURS_QUERY = 'SELECT * FROM [dbo].[inventtable] WHERE [modifieddatetime] >= DATEADD(MINUTE, -48, GETDATE()) ORDER BY [modifieddatetime] ASC';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_event_processing_service_resolves_azure_sql_driver_for_generic_platform(): void
    {
        $platform = Platform::query()->create([
            'name' => 'Azure SQL MACO',
            'slug' => 'azure-sql-maco',
            'type' => 'generic',
            'settings' => [
                'service_driver' => 'azure_sql',
            ],
            'active' => true,
        ]);

        $serviceClass = app(EventProcessingService::class)->getServiceClass($platform);

        $this->assertSame(AzureSqlService::class, $serviceClass);
    }

    public function test_azure_sql_service_applies_hubspot_runtime_token_from_next_event_platform(): void
    {
        config()->set('hubspot.access_token', null);

        $hubspotPlatform = Platform::query()->create([
            'name' => 'Hubspot corripio',
            'slug' => 'hubspot-corripio',
            'type' => 'hubspot',
            'credentials' => [
                'access_token' => 'hubspot-token-from-next-platform',
            ],
            'active' => true,
        ]);

        [$platform, $event] = $this->makeAzureSqlEvent('syncProducts', 'azure_sql.products.sync');

        $nextEvent = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Actualización de productos',
            'event_type_id' => 'product.updated',
            'type' => 'webhook',
            'active' => true,
        ]);

        $event->update([
            'to_event_id' => $nextEvent->id,
        ]);

        new AzureSqlService($platform, $event->fresh('to_event.platform'), null, Mockery::mock(HubspotApiServiceRefactored::class));

        $this->assertSame('hubspot-token-from-next-platform', config('hubspot.access_token'));
    }

    public function test_sync_products_uses_identificador_db_then_sku_and_updates_existing_product(): void
    {
        [$platform, $event] = $this->makeAzureSqlEvent('syncProducts', 'azure_sql.products.sync');
        $record = $this->makeRecord($event);

        $this->attachRelationship($event, $platform, 'ProductName', 'name');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')
            ->once()
            ->with(self::PRODUCTS_DEFAULT_QUERY)
            ->andReturn([
                (object) [
                    'itemid' => 'SKU-001',
                    'ProductName' => 'Teclado mecanico',
                ],
            ]);

        DB::shouldReceive('purge')->once()->with('azure_sql_runtime');
        DB::shouldReceive('connection')->once()->with('azure_sql_runtime')->andReturn($connection);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('products', 'identificador_db', 'SKU-001', ['identificador_db'])
            ->andReturn(['success' => true, 'data' => ['results' => []]]);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('products', 'sku', 'SKU-001', ['sku'])
            ->andReturn(['success' => true, 'data' => ['results' => [['id' => '2001']]]]);
        $hubspotApi->shouldReceive('updateObject')
            ->once()
            ->with('products', '2001', ['name' => 'Teclado mecanico'])
            ->andReturn(['success' => true, 'data' => ['id' => '2001']]);

        $service = new AzureSqlService($platform, $event, $record, $hubspotApi);
        $result = $service->syncProducts();

        $this->assertTrue($result['success']);
        $this->assertSame(1, data_get($result, 'data.rows_updated'));
        $this->assertSame(['2001'], data_get($result, 'data.hubspot_updated_ids'));
        $this->assertSame(24, data_get($result, 'data.sync_window_hours'));
    }

    public function test_sync_products_prepares_batch_output_when_next_event_is_hubspot_product_update(): void
    {
        $hubspotPlatform = Platform::query()->create([
            'name' => 'Hubspot corripio',
            'slug' => 'hubspot-corripio',
            'type' => 'hubspot',
            'credentials' => [
                'access_token' => 'token_123',
            ],
            'active' => true,
        ]);

        [$platform, $event] = $this->makeAzureSqlEvent('syncProducts', 'azure_sql.products.sync');
        $nextEvent = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Actualización de productos',
            'event_type_id' => 'product.updated',
            'type' => 'webhook',
            'active' => true,
        ]);

        $event->update([
            'to_event_id' => $nextEvent->id,
        ]);

        $record = $this->makeRecord($event);

        $this->attachRelationship($event, $platform, 'itemid', 'identificador_db');
        $this->attachRelationship($event, $platform, 'ProductName', 'name');
        $this->attachRelationship($event, $platform, 'modifieddatetime', 'date_modificacion_db');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')
            ->once()
            ->with(self::PRODUCTS_DEFAULT_QUERY)
            ->andReturn([
                (object) [
                    'itemid' => 'SKU-001',
                    'ProductName' => 'Teclado mecanico',
                    'modifieddatetime' => '2026-05-13 10:15:00',
                ],
            ]);

        DB::shouldReceive('purge')->once()->with('azure_sql_runtime');
        DB::shouldReceive('connection')->once()->with('azure_sql_runtime')->andReturn($connection);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldNotReceive('searchObjectByProperty');
        $hubspotApi->shouldNotReceive('updateObject');

        $service = new AzureSqlService($platform, $event->fresh('to_event.platform'), $record, $hubspotApi);
        $result = $service->syncProducts();

        $this->assertTrue($result['success']);
        $this->assertSame(1, data_get($result, 'data.output_payload_count'));
        $this->assertSame('SKU-001', data_get($result, 'data.output_payload.0.identificador_db'));
        $this->assertSame('Teclado mecanico', data_get($result, 'data.output_payload.0.name'));
        $this->assertSame('2026-05-13 10:15:00', data_get($result, 'data.output_payload.0.date_modificacion_db'));
        $this->assertSame('next_event', data_get($result, 'data.dispatch_mode'));
    }

    public function test_sync_products_can_use_a_48_hour_modifieddatetime_window(): void
    {
        [$platform, $event] = $this->makeAzureSqlEvent('syncProducts', 'azure_sql.products.sync', [
            'sync_window_hours' => 48,
        ]);
        $record = $this->makeRecord($event);

        $this->attachRelationship($event, $platform, 'ProductName', 'name');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')
            ->once()
            ->with(self::PRODUCTS_48_HOURS_QUERY)
            ->andReturn([
                (object) [
                    'itemid' => 'SKU-048',
                    'ProductName' => 'Producto reciente',
                ],
            ]);

        DB::shouldReceive('purge')->once()->with('azure_sql_runtime');
        DB::shouldReceive('connection')->once()->with('azure_sql_runtime')->andReturn($connection);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('products', 'identificador_db', 'SKU-048', ['identificador_db'])
            ->andReturn(['success' => true, 'data' => ['results' => [['id' => '20048']]]]);
        $hubspotApi->shouldReceive('updateObject')
            ->once()
            ->with('products', '20048', ['name' => 'Producto reciente'])
            ->andReturn(['success' => true, 'data' => ['id' => '20048']]);

        $service = new AzureSqlService($platform, $event, $record, $hubspotApi);
        $result = $service->syncProducts();

        $this->assertTrue($result['success']);
        $this->assertSame(48, data_get($result, 'data.sync_window_hours'));
    }

    public function test_sync_accounts_skips_hubspot_owned_columns_and_matches_by_email_when_identifier_misses(): void
    {
        [$platform, $event] = $this->makeAzureSqlEvent('syncAccounts', 'azure_sql.accounts.sync');
        $record = $this->makeRecord($event);

        $this->attachRelationship($event, $platform, 'custname', 'name');
        $this->attachRelationship($event, $platform, 'Cadenas_Empresas', 'cadena_empresas');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')
            ->once()
            ->with('SELECT * FROM [dbo].[custtable]')
            ->andReturn([
                (object) [
                    'accountnum' => 'ACCT-100',
                    'custname' => 'Cliente Norte',
                    'Correo' => 'cliente@example.com',
                    'Telefono' => '8095550101',
                    'Cadenas_Empresas' => 'No debe sobrescribirse',
                ],
            ]);

        DB::shouldReceive('purge')->once()->with('azure_sql_runtime');
        DB::shouldReceive('connection')->once()->with('azure_sql_runtime')->andReturn($connection);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('companies', 'identificador_db', 'ACCT-100', ['identificador_db'])
            ->andReturn(['success' => true, 'data' => ['results' => []]]);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('companies', 'email', 'cliente@example.com', ['email'])
            ->andReturn(['success' => true, 'data' => ['results' => [['id' => 'cmp-10']]]]);
        $hubspotApi->shouldReceive('updateObject')
            ->once()
            ->with('companies', 'cmp-10', ['name' => 'Cliente Norte'])
            ->andReturn(['success' => true, 'data' => ['id' => 'cmp-10']]);

        $service = new AzureSqlService($platform, $event, $record, $hubspotApi);
        $result = $service->syncAccounts();

        $this->assertTrue($result['success']);
        $this->assertSame(1, data_get($result, 'data.rows_updated'));
    }

    public function test_sync_contacts_does_not_overwrite_locator_or_tipo_from_sql(): void
    {
        [$platform, $event] = $this->makeAzureSqlEvent('syncContacts', 'azure_sql.contacts.sync');
        $record = $this->makeRecord($event);

        $this->attachRelationship($event, $platform, 'accountnum', 'identificador_db');
        $this->attachRelationship($event, $platform, 'locator', 'phone');
        $this->attachRelationship($event, $platform, 'Tipo', 'tipo_locator');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')
            ->once()
            ->with('SELECT * FROM [dbo].[contactos_cl]')
            ->andReturn([
                (object) [
                    'accountnum' => 'CT-77',
                    'locator' => 'persona@example.com',
                    'Tipo' => 'correo',
                ],
            ]);

        DB::shouldReceive('purge')->once()->with('azure_sql_runtime');
        DB::shouldReceive('connection')->once()->with('azure_sql_runtime')->andReturn($connection);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldReceive('searchObjectByProperty')
            ->once()
            ->with('contacts', 'identificador_db', 'CT-77', ['identificador_db'])
            ->andReturn(['success' => true, 'data' => ['results' => [['id' => 'ct-77']]]]);
        $hubspotApi->shouldReceive('updateObject')
            ->once()
            ->with('contacts', 'ct-77', ['identificador_db' => 'CT-77'])
            ->andReturn(['success' => true, 'data' => ['id' => 'ct-77']]);

        $service = new AzureSqlService($platform, $event, $record, $hubspotApi);
        $result = $service->syncContacts();

        $this->assertTrue($result['success']);
        $this->assertSame(1, data_get($result, 'data.rows_updated'));
    }

    public function test_execute_event_job_logs_warning_when_azure_sql_service_returns_warning(): void
    {
        [$platform, $event] = $this->makeAzureSqlEvent('syncProducts', 'azure_sql.products.sync');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')
            ->once()
            ->with(self::PRODUCTS_DEFAULT_QUERY)
            ->andReturn([]);

        DB::shouldReceive('purge')->once()->with('azure_sql_runtime');
        DB::shouldReceive('connection')->once()->with('azure_sql_runtime')->andReturn($connection);

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $this->app->instance(HubspotApiServiceRefactored::class, $hubspotApi);

        $job = new ExecuteEventJob($event);
        $job->handle(app(EventLoggingService::class), app(EventProcessingService::class));

        $record = $event->records()->latest('id')->first();

        $this->assertNotNull($record);
        $this->assertSame('warning', $record->status);
        $this->assertSame('inventtable', data_get($record->details, 'table'));
    }

    public function test_execute_event_job_dispatches_next_event_job_when_output_payload_is_prepared(): void
    {
        Queue::fake();

        $hubspotPlatform = Platform::query()->create([
            'name' => 'Hubspot corripio',
            'slug' => 'hubspot-corripio',
            'type' => 'hubspot',
            'credentials' => [
                'access_token' => 'token_123',
            ],
            'active' => true,
        ]);

        [$platform, $event] = $this->makeAzureSqlEvent('syncProducts', 'azure_sql.products.sync');
        $nextEvent = Event::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => 'Actualización de productos',
            'event_type_id' => 'product.updated',
            'type' => 'webhook',
            'active' => true,
        ]);

        $event->update([
            'to_event_id' => $nextEvent->id,
        ]);

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')
            ->once()
            ->with(self::PRODUCTS_DEFAULT_QUERY)
            ->andReturn([
                (object) [
                    'itemid' => 'SKU-001',
                    'ProductName' => 'Teclado mecanico',
                ],
            ]);

        DB::shouldReceive('purge')->once()->with('azure_sql_runtime');
        DB::shouldReceive('connection')->once()->with('azure_sql_runtime')->andReturn($connection);

        $this->attachRelationship($event, $platform, 'itemid', 'identificador_db');
        $this->attachRelationship($event, $platform, 'ProductName', 'name');

        $hubspotApi = Mockery::mock(HubspotApiServiceRefactored::class);
        $hubspotApi->shouldNotReceive('searchObjectByProperty');
        $hubspotApi->shouldNotReceive('updateObject');
        $this->app->instance(HubspotApiServiceRefactored::class, $hubspotApi);

        $job = new ExecuteEventJob($event->fresh('to_event.platform'));
        $job->handle(app(EventLoggingService::class), app(EventProcessingService::class));

        Queue::assertPushed(ProcessNextEventJob::class, function (ProcessNextEventJob $job) use ($event): bool {
            return $job->event->id === $event->id
                && ($job->data[0]['identificador_db'] ?? null) === 'SKU-001'
                && ($job->data[0]['name'] ?? null) === 'Teclado mecanico';
        });
    }

    private function makeAzureSqlEvent(string $methodName, string $eventTypeId, array $meta = []): array
    {
        $platform = Platform::query()->create([
            'name' => 'Azure SQL MACO',
            'slug' => 'azure-sql-maco',
            'type' => 'generic',
            'credentials' => [
                'service_driver' => 'azure_sql',
                'username' => 'sqladmin',
                'password' => 'secret',
            ],
            'settings' => [
                'service_driver' => 'azure_sql',
                'host' => 'sql-crm-maco.database.windows.net',
                'port' => '1433',
                'database' => 'DB-CRM',
                'encrypt' => true,
                'trust_server_certificate' => false,
                'login_timeout' => 30,
            ],
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => $eventTypeId,
            'event_type_id' => $eventTypeId,
            'method_name' => $methodName,
            'type' => 'schedule',
            'schedule_expression' => '0 * * * *',
            'meta' => $meta,
            'active' => true,
        ]);

        return [$platform, $event];
    }

    private function makeRecord(Event $event)
    {
        return $event->records()->create([
            'event_type' => $event->event_type_id,
            'status' => 'init',
            'payload' => [],
            'message' => 'Init',
        ]);
    }

    private function attachRelationship(Event $event, Platform $sourcePlatform, string $sourceKey, string $targetKey): void
    {
        $hubspotPlatform = Platform::query()->firstOrCreate([
            'slug' => 'hubspot-test',
        ], [
            'name' => 'HubSpot Test',
            'type' => 'hubspot',
            'active' => true,
        ]);

        $sourceProperty = Property::query()->create([
            'platform_id' => $sourcePlatform->id,
            'name' => $sourceKey,
            'key' => $sourceKey,
            'type' => 'string',
            'required' => false,
            'active' => true,
        ]);

        $targetProperty = Property::query()->create([
            'platform_id' => $hubspotPlatform->id,
            'name' => $targetKey,
            'key' => $targetKey,
            'type' => 'string',
            'required' => false,
            'active' => true,
        ]);

        PropertyRelationship::query()->create([
            'event_id' => $event->id,
            'property_id' => $sourceProperty->id,
            'related_property_id' => $targetProperty->id,
            'mapping_key' => null,
            'active' => true,
        ]);
    }
}
