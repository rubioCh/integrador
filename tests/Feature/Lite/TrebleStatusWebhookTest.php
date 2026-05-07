<?php

namespace Tests\Feature\Lite;

use App\Models\Client;
use App\Models\PlatformConnection;
use App\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrebleStatusWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_treble_callback_requires_valid_secret(): void
    {
        [$client] = $this->seedClientAndConnection();

        $response = $this->postJson('/webhooks/' . $client->slug . '/treble/status', [
            'event_id' => 'evt-1',
            'event_type' => 'session.close',
            'session' => ['external_id' => 'ext-1'],
        ], [
            'X-Treble-Webhook-Secret' => 'wrong',
        ]);

        $response->assertStatus(401);
    }

    public function test_treble_callback_updates_matching_record_by_external_id(): void
    {
        [$client] = $this->seedClientAndConnection();

        $record = Record::query()->create([
            'client_id' => $client->id,
            'event_type' => 'contact.propertyChange',
            'status' => 'success',
            'payload' => [],
            'message' => 'Treble template dispatched successfully.',
            'details' => [
                'treble_response' => [
                    'external_id' => 'ext-123',
                ],
                'treble_request' => [
                    'template_name' => 'bienvenida',
                    'phone' => '9993543628',
                ],
            ],
        ]);

        $payload = [
            'event_id' => 'evt-123',
            'timestamp' => '2026-05-04T23:03:09.195065Z',
            'event_type' => 'session.close',
            'session' => [
                'external_id' => 'ext-123',
                'closed_at' => '2026-05-04T23:03:00Z',
            ],
            'user' => [
                'country_code' => '+52',
                'cellphone' => '19993543628',
            ],
            'hsm' => [
                'name' => 'bienvenida',
            ],
        ];

        $response = $this->postJson('/webhooks/' . $client->slug . '/treble/status', $payload, [
            'X-Treble-Webhook-Secret' => 'status-secret',
        ]);

        $response->assertOk();

        $record->refresh();
        $this->assertSame('session.close', $record->details['treble_status']['current'] ?? null);
        $this->assertSame('ext-123', $record->details['treble_status']['external_id'] ?? null);
        $this->assertCount(1, $record->details['treble_status']['history'] ?? []);
    }

    public function test_treble_callback_is_idempotent_by_event_id(): void
    {
        [$client] = $this->seedClientAndConnection();

        $record = Record::query()->create([
            'client_id' => $client->id,
            'event_type' => 'contact.propertyChange',
            'status' => 'success',
            'payload' => [],
            'message' => 'Treble template dispatched successfully.',
            'details' => [
                'treble_response' => [
                    'external_id' => 'ext-123',
                ],
            ],
        ]);

        $payload = [
            'event_id' => 'evt-123',
            'timestamp' => '2026-05-04T23:03:09.195065Z',
            'event_type' => 'session.close',
            'session' => [
                'external_id' => 'ext-123',
            ],
        ];

        $headers = ['X-Treble-Webhook-Secret' => 'status-secret'];
        $this->postJson('/webhooks/' . $client->slug . '/treble/status', $payload, $headers)->assertOk();
        $this->postJson('/webhooks/' . $client->slug . '/treble/status', $payload, $headers)->assertOk();

        $record->refresh();
        $this->assertCount(1, $record->details['treble_status']['history'] ?? []);
    }

    public function test_treble_connection_generates_secret_automatically_when_api_key_is_saved(): void
    {
        $client = Client::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'active' => true,
        ]);

        $connection = PlatformConnection::query()->create([
            'client_id' => $client->id,
            'platform_type' => 'treble',
            'name' => 'Treble',
            'slug' => 'treble',
            'base_url' => 'https://main.treble.ai',
            'credentials' => ['api_key' => 'api-key'],
            'settings' => [
                'send_path' => '/deployment/api/poll/{poll_id}',
                'auth_mode' => 'bearer_api_key',
            ],
            'signature_header' => 'X-Treble-Webhook-Secret',
            'webhook_secret' => PlatformConnection::generateWebhookSecret(),
            'active' => true,
        ]);

        $this->assertSame('X-Treble-Webhook-Secret', $connection->signature_header);
        $this->assertNotEmpty($connection->webhook_secret);
    }

    private function seedClientAndConnection(): array
    {
        $client = Client::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'active' => true,
        ]);

        $connection = PlatformConnection::query()->create([
            'client_id' => $client->id,
            'platform_type' => 'treble',
            'name' => 'Treble',
            'slug' => 'treble',
            'base_url' => 'https://main.treble.ai',
            'signature_header' => 'X-Treble-Webhook-Secret',
            'webhook_secret' => 'status-secret',
            'credentials' => ['api_key' => 'treble-token'],
            'settings' => [
                'send_path' => '/deployment/api/poll/{poll_id}',
                'auth_mode' => 'bearer_api_key',
            ],
            'active' => true,
        ]);

        return [$client, $connection];
    }
}
