<?php

namespace Tests\Feature;

use App\Services\NetSuite\NetSuiteApiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NetSuiteApiServiceTest extends TestCase
{
    public function test_ping_sends_oauth_header_and_returns_success(): void
    {
        config()->set('netsuite.account', 'acct_123');
        config()->set('netsuite.consumer_key', 'consumer_key');
        config()->set('netsuite.consumer_secret', 'consumer_secret');
        config()->set('netsuite.token_id', 'token_id');
        config()->set('netsuite.token_secret', 'token_secret');
        config()->set('netsuite.base_url', 'https://netsuite.test/services/rest');

        Http::fake(function ($request) {
            $authHeader = $request->header('Authorization')[0] ?? '';
            $this->assertStringContainsString('OAuth', $authHeader);
            $this->assertStringContainsString('oauth_signature=', $authHeader);

            return Http::response([
                'items' => [],
            ], 200);
        });

        $service = app(NetSuiteApiService::class);
        $result = $service->ping();

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['status_code']);
    }
}
