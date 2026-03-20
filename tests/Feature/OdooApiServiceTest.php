<?php

namespace Tests\Feature;

use App\Services\Odoo\OdooApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OdooApiServiceTest extends TestCase
{
    public function test_execute_kw_returns_data_when_login_and_call_are_successful(): void
    {
        config()->set('odoo.url', 'https://odoo.test');
        config()->set('odoo.database', 'odoo_db');
        config()->set('odoo.username', 'odoo_user');
        config()->set('odoo.password', 'odoo_pass');

        Http::fake([
            'https://odoo.test/jsonrpc' => Http::sequence()
                ->push(['result' => 99], 200)
                ->push(['result' => [['id' => 1, 'name' => 'A']]], 200),
        ]);

        $service = app(OdooApiService::class);
        $result = $service->executeKw(
            'res.partner',
            'search_read',
            [[]],
            ['fields' => ['name']]
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, count($result['data']['result']));
    }
}
