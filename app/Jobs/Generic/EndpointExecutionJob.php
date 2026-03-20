<?php

namespace App\Jobs\Generic;

use App\Models\Event;
use App\Models\EventIdempotencyKey;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\Generic\GenericHttpAdapter;
use App\Services\Generic\GenericPlatformService;
use App\Services\RateLimitService;
use App\Jobs\ProcessNextEventJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EndpointExecutionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        public Event $event,
        public Record $record,
        public array $payload
    ) {
        $this->onQueue('processing');
    }

    public function handle(
        GenericPlatformService $genericPlatformService,
        GenericHttpAdapter $httpAdapter,
        EventLoggingService $eventLoggingService,
        RateLimitService $rateLimitService
    ): void {
        $this->record->update([
            'status' => 'processing',
            'message' => 'Executing generic endpoint',
        ]);

        $endpoint = $genericPlatformService->resolveEndpoint($this->event);
        $method = $genericPlatformService->resolveMethod($this->event);
        $headers = $genericPlatformService->resolveHeaders($this->event, $this->event->platform);
        $query = $genericPlatformService->resolveQueryParams($this->event, $this->payload);
        $body = $genericPlatformService->resolveBody($this->event, $this->payload);
        $timeout = $genericPlatformService->resolveTimeout($this->event);
        $retryPolicy = $genericPlatformService->resolveRetryPolicy($this->event);
        $idempotencyPolicy = $genericPlatformService->resolveIdempotencyPolicy($this->event);
        $idempotency = $this->acquireIdempotencyKey($endpoint, $method, $idempotencyPolicy, $eventLoggingService);
        if ($idempotency['skip']) {
            return;
        }
        $idempotencyModel = $idempotency['model'];

        $response = $httpAdapter->send(
            $this->event->platform?->type ?? 'generic',
            $endpoint,
            $method,
            $headers,
            $query,
            $body,
            $timeout,
            $retryPolicy
        );

        if ($response['retryable'] && $this->attempts() < $this->tries) {
            $retryAfter = $this->extractRetryAfter($response);
            $backoffMs = $rateLimitService->computeBackoffMs($this->attempts(), $retryAfter);
            $rateLimitService->logBackoff($this->event->platform?->type ?? 'generic', $endpoint, $response['status_code'], $retryAfter, $this->attempts(), $backoffMs);
            $eventLoggingService->logEventWarning($this->record, 'Retryable response received, re-queuing job.');
            $this->updateIdempotencyStatus($idempotencyModel, 'failed_retryable', [
                'status_code' => $response['status_code'],
                'request_id' => $response['request_id'],
                'attempt' => $response['attempt'],
            ]);
            $this->release((int) ceil($backoffMs / 1000));
            return;
        }

        $recordStatus = $response['success'] ? 'success' : ($response['retryable'] ? 'warning' : 'error');
        $this->record->update([
            'status' => $recordStatus,
            'message' => $response['success'] ? 'Endpoint execution completed' : ($response['error']['message'] ?? 'Endpoint execution failed'),
            'details' => [
                'response' => $this->sanitizeResponse($response),
                'request' => [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'headers' => $this->sanitizeHeaders($headers),
                    'query' => $query,
                ],
            ],
        ]);

        $idempotencyStatus = $response['success']
            ? 'success'
            : ($response['retryable'] ? 'failed_retryable' : 'failed');
        $this->updateIdempotencyStatus($idempotencyModel, $idempotencyStatus, [
            'status_code' => $response['status_code'],
            'request_id' => $response['request_id'],
            'attempt' => $response['attempt'],
            'record_status' => $recordStatus,
        ]);

        if ($response['success'] && $this->event->to_event) {
            ProcessNextEventJob::dispatch($this->event, $this->record, $this->payload)->onQueue('events');
        }
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = config('generic-platforms.policy.sensitive_headers', []);
        $sanitized = [];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }
            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function sanitizeResponse(array $response): array
    {
        $response['error']['details'] = $this->sanitizeErrorDetails($response['error']['details'] ?? null);

        return $response;
    }

    private function sanitizeErrorDetails(?string $details): ?string
    {
        if (! is_string($details)) {
            return $details;
        }

        $sensitive = config('generic-platforms.policy.sensitive_headers', []);
        foreach ($sensitive as $header) {
            $pattern = '/' . preg_quote($header, '/') . '\\s*:\\s*[^\\n\\r]*/i';
            $details = preg_replace($pattern, $header . ': [redacted]', $details);
        }

        return $details;
    }

    private function acquireIdempotencyKey(
        string $endpoint,
        string $method,
        array $policy,
        EventLoggingService $eventLoggingService
    ): array
    {
        if (! (bool) ($policy['enabled'] ?? false)) {
            return [
                'skip' => false,
                'model' => null,
            ];
        }

        $template = (string) ($policy['key_template'] ?? '{event_id}:{record_id}:{method}:{endpoint}');
        $key = $this->buildIdempotencyKey($endpoint, $method, $template);
        $ttlHours = max(1, (int) ($policy['ttl_hours'] ?? 24));
        $expiresAt = now()->addHours($ttlHours);

        $state = DB::transaction(function () use ($key, $endpoint, $method, $expiresAt): array {
            $existing = EventIdempotencyKey::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                $created = EventIdempotencyKey::query()->create([
                    'idempotency_key' => $key,
                    'event_id' => $this->event->id,
                    'record_id' => $this->record->id,
                    'endpoint' => $endpoint,
                    'method' => strtoupper($method),
                    'status' => 'processing',
                    'expires_at' => $expiresAt,
                ]);

                return [
                    'model' => $created,
                    'state' => 'acquired',
                ];
            }

            $isExpired = $existing->expires_at?->isPast() ?? false;

            if (! $isExpired && $existing->status === 'success') {
                return [
                    'model' => $existing,
                    'state' => 'already_processed',
                ];
            }

            if (! $isExpired && $existing->status === 'processing') {
                return [
                    'model' => $existing,
                    'state' => 'already_processing',
                ];
            }

            $existing->update([
                'event_id' => $this->event->id,
                'record_id' => $this->record->id,
                'endpoint' => $endpoint,
                'method' => strtoupper($method),
                'status' => 'processing',
                'expires_at' => $expiresAt,
            ]);

            return [
                'model' => $existing->fresh(),
                'state' => 'acquired',
            ];
        });

        if ($state['state'] === 'already_processed') {
            $eventLoggingService->logEventWarning($this->record, 'Idempotent request already processed successfully.');

            return [
                'skip' => true,
                'model' => $state['model'],
            ];
        }

        if ($state['state'] === 'already_processing') {
            $eventLoggingService->logEventWarning($this->record, 'Idempotent request already in progress.');

            return [
                'skip' => true,
                'model' => $state['model'],
            ];
        }

        return [
            'skip' => false,
            'model' => $state['model'],
        ];
    }

    private function updateIdempotencyStatus(?EventIdempotencyKey $model, string $status, array $metadata = []): void
    {
        if (! $model) {
            return;
        }

        $existingMetadata = is_array($model->metadata) ? $model->metadata : [];
        $model->update([
            'status' => $status,
            'metadata' => array_merge($existingMetadata, $metadata),
        ]);
    }

    private function buildIdempotencyKey(string $endpoint, string $method, string $template): string
    {
        $path = parse_url($endpoint, PHP_URL_PATH) ?: $endpoint;

        $raw = strtr($template, [
            '{event_id}' => (string) $this->event->id,
            '{record_id}' => (string) $this->record->id,
            '{method}' => strtoupper($method),
            '{path}' => (string) $path,
            '{endpoint}' => $endpoint,
        ]);

        return 'evt:' . $this->event->id . ':idem:' . Str::lower(sha1($raw));
    }

    private function extractRetryAfter(array $response): ?int
    {
        $details = $response['error']['details'] ?? null;
        if (! $details || ! is_string($details)) {
            return null;
        }

        if (preg_match('/retry-after:\\s*(\\d+)/i', $details, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
