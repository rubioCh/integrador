<?php

namespace Tests\Feature;

use App\Jobs\WebhookCustomProcessJob;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_with_valid_signature_is_accepted_and_queued(): void
    {
        Queue::fake();

        Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'signature' => 'x-signature',
            'secret_key' => 'webhook-secret',
            'active' => true,
        ]);

        $payload = [
            'subscriptionType' => 'company.created',
            'objectId' => 1001,
        ];

        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash('sha256', 'webhook-secret' . $rawPayload);

        $response = $this->call(
            'POST',
            '/webhooks/hubspot',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-SIGNATURE' => $signature,
            ],
            $rawPayload
        );

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Webhook received',
        ]);

        $this->assertDatabaseCount('webhook_calls', 1);
        Queue::assertPushed(WebhookCustomProcessJob::class);
    }
}

