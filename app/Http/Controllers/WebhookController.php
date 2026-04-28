<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\HubSpot\ContactPropertyChangedEvent;
use App\Models\Client;
use App\Models\Platform;
use App\Models\PlatformConnection;
use App\WebhookClient\WebhookProcessor;
use Spatie\WebhookClient\WebhookConfig;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function handleWebhook(Client $client, string $platform, Request $request): Response
    {
        $connection = PlatformConnection::query()
            ->where('client_id', $client->id)
            ->where('platform_type', $platform)
            ->where('active', true)
            ->first();

        if (! $connection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook not received, platform connection not found.',
            ], 404);
        }

        if ($platform !== 'hubspot') {
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook platform not supported in Lite mode.',
            ], 422);
        }

        if (! $this->isValidSignature($request, $connection)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $payloads = $this->extractPayloads($request->all());
        foreach ($payloads as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            event(new ContactPropertyChangedEvent($client, $connection, $payload));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received',
        ], 200);
    }

    public function handleLegacyWebhook(string $platform, Request $request): Response
    {
        $platformModel = Platform::query()->where('slug', $platform)->first();

        if (! $platformModel) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook not received, platform not found.',
            ], 400);
        }

        if (! $platformModel->secret_key || ! $platformModel->signature) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webhook not received, missing secret key or signature configuration.',
            ], 400);
        }

        $config = new WebhookConfig([
            'name' => $platformModel->slug,
            'signing_secret' => $platformModel->secret_key,
            'signature_header_name' => $platformModel->signature,
            'signature_validator' => \App\WebhookClient\WebhookCustomSignatureValidator::class,
            'webhook_profile' => \App\WebhookClient\WebhookCustomProfile::class,
            'webhook_response' => \App\WebhookClient\WebhookCustomResponse::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'store_headers' => ['platform' => json_encode($platformModel->only(['id', 'name', 'slug', 'type']))],
            'process_webhook_job' => \App\Jobs\WebhookCustomProcessJob::class,
        ]);

        $processor = new WebhookProcessor($request, $config);
        $processor->process();

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received',
        ], 200);
    }

    private function isValidSignature(Request $request, PlatformConnection $connection): bool
    {
        $headerName = (string) ($connection->signature_header ?? '');
        $secret = (string) ($connection->webhook_secret ?? '');

        if ($headerName === '' || $secret === '') {
            return false;
        }

        $signature = $request->header($headerName) ?? $request->query($headerName);
        if (! is_string($signature) || trim($signature) === '') {
            return false;
        }

        $computedSignature = hash('sha256', $secret . $request->getContent());

        return hash_equals($signature, $computedSignature);
    }

    private function extractPayloads(array $payload): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'items', 'results', 'objects', 'records', 'entities'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        return [$payload];
    }
}
