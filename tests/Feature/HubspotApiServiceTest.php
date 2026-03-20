<?php

namespace Tests\Feature;

use App\Services\Hubspot\HubspotApiServiceRefactored;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubspotApiServiceTest extends TestCase
{
    public function test_ping_returns_success_when_hubspot_api_responds_ok(): void
    {
        config()->set('hubspot.access_token', 'token_123');
        config()->set('hubspot.base_url', 'https://api.hubapi.test');

        Http::fake([
            'https://api.hubapi.test/integrations/v1/me' => Http::response([
                'portalId' => 12345,
            ], 200),
        ]);

        $service = app(HubspotApiServiceRefactored::class);
        $result = $service->ping();

        $this->assertTrue($result['success']);
        $this->assertSame(200, $result['status_code']);
        $this->assertSame(12345, $result['data']['portalId']);
    }

    public function test_search_signed_quotes_uses_configured_filter(): void
    {
        config()->set('hubspot.access_token', 'token_123');
        config()->set('hubspot.base_url', 'https://api.hubapi.test');
        config()->set('hubspot.signed_quotes.status_property', 'hs_status');
        config()->set('hubspot.signed_quotes.signed_status_value', 'SIGNED');

        Http::fake([
            'https://api.hubapi.test/crm/v3/objects/quotes/search' => Http::response([
                'results' => [
                    ['id' => 'q1'],
                ],
            ], 200),
        ]);

        $service = app(HubspotApiServiceRefactored::class);
        $result = $service->searchSignedQuotes();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']['results']);
    }

    public function test_add_note_to_object_creates_associated_note(): void
    {
        config()->set('hubspot.access_token', 'token_123');
        config()->set('hubspot.base_url', 'https://api.hubapi.test');
        config()->set('hubspot.note_association_type_ids.contacts', 202);

        Http::fake([
            'https://api.hubapi.test/crm/v3/objects/notes' => Http::response([
                'id' => '9001',
            ], 201),
        ]);

        $service = app(HubspotApiServiceRefactored::class);
        $result = $service->addNoteToObject('contacts', '123', 'Contact sync failed', [
            'event_id' => 10,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(201, $result['status_code']);
        $this->assertSame('9001', $result['data']['id']);
    }
}
