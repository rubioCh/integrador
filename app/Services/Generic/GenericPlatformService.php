<?php

namespace App\Services\Generic;

use App\Models\Event;
use App\Models\EventHttpConfig;
use App\Models\Platform;
use App\Models\Record;
use App\Services\Base\BaseService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GenericPlatformService extends BaseService implements GenericPlatformPort
{
    public function __construct(
        Platform $platform,
        ?Event $event = null,
        ?Record $record = null,
        protected AuthStrategyResolver $authStrategyResolver
    ) {
        parent::__construct($platform, $event, $record);
    }

    public function resolveEndpoint(Event $event): string
    {
        $httpConfig = $this->getHttpConfig($event);
        $configBaseUrl = trim((string) ($httpConfig?->base_url ?? ''));
        $configPath = trim((string) ($httpConfig?->path ?? ''));

        if ($configBaseUrl !== '' || $configPath !== '') {
            if (Str::startsWith($configPath, ['http://', 'https://'])) {
                return $configPath;
            }

            if ($configBaseUrl !== '' && $configPath !== '') {
                return rtrim($configBaseUrl, '/') . '/' . ltrim($configPath, '/');
            }

            if ($configBaseUrl !== '') {
                return $configBaseUrl;
            }
        }

        $endpoint = (string) ($event->meta['endpoint'] ?? $event->endpoint_api ?? '');
        if ($endpoint === '') {
            throw new \RuntimeException('Missing endpoint for generic platform event.');
        }

        if (Str::startsWith($endpoint, ['http://', 'https://'])) {
            return $endpoint;
        }

        $baseUrl = (string) ($event->platform?->settings['base_url'] ?? $event->platform?->settings['api_url'] ?? '');
        if ($baseUrl === '') {
            return $endpoint;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    public function resolveMethod(Event $event): string
    {
        $httpConfig = $this->getHttpConfig($event);
        $method = $httpConfig?->method
            ?? $event->meta['http_method']
            ?? $event->meta['method']
            ?? $event->method_name
            ?? 'POST';

        return strtoupper((string) $method);
    }

    public function resolveHeaders(Event $event, Platform $platform): array
    {
        $headers = (array) ($platform->settings['headers'] ?? []);
        $httpConfig = $this->getHttpConfig($event);
        $configHeaders = (array) ($httpConfig?->headers_json ?? []);
        $eventHeaders = (array) ($event->meta['headers'] ?? []);

        $headers = array_merge($headers, $configHeaders, $eventHeaders);

        $authMode = $this->authStrategyResolver->resolveAuthMode($platform, $event);
        if ($authMode === 'bearer_api_key') {
            $headers = array_merge($headers, $this->authStrategyResolver->buildBearerAuthHeaders($platform, $event));
        } elseif ($authMode === 'basic_auth') {
            $headers = array_merge($headers, $this->authStrategyResolver->buildBasicAuthHeaders($platform, $event));
        } elseif ($authMode === 'oauth2_client_credentials') {
            $headers = array_merge($headers, $this->authStrategyResolver->buildOAuth2Headers($platform, $event));
        }

        return $headers;
    }

    public function resolveQueryParams(Event $event, array $payload): array
    {
        $httpConfig = $this->getHttpConfig($event);
        $configQuery = (array) ($httpConfig?->query_json ?? []);
        $query = (array) ($event->meta['query'] ?? []);
        $payloadQuery = (array) ($payload['query'] ?? []);

        return array_merge($configQuery, $query, $payloadQuery);
    }

    public function resolveBody(Event $event, array $payload): array
    {
        $mapping = (array) ($event->payload_mapping ?? []);

        if (empty($mapping)) {
            return $payload;
        }

        $body = [];
        foreach ($mapping as $sourceKey => $targetKey) {
            $value = Arr::get($payload, $sourceKey);
            if ($value === null) {
                continue;
            }
            Arr::set($body, $targetKey, $value);
        }

        return $body;
    }

    public function resolveTimeout(Event $event): int
    {
        $httpConfig = $this->getHttpConfig($event);

        return (int) ($httpConfig?->timeout_seconds
            ?? $event->meta['timeout']
            ?? config('generic-platforms.policy.timeout_seconds', 30));
    }

    public function resolveRetryPolicy(Event $event): array
    {
        $default = config('generic-platforms.policy.retry', []);
        $httpConfig = $this->getHttpConfig($event);
        $configRetry = (array) ($httpConfig?->retry_policy_json ?? []);
        $override = (array) ($event->meta['retry'] ?? []);

        return array_merge($default, $configRetry, $override);
    }

    public function resolveIdempotencyPolicy(Event $event): array
    {
        $eventMeta = is_array($event->meta) ? $event->meta : [];
        $httpConfig = $this->getHttpConfig($event);
        $default = [
            'enabled' => (bool) ($eventMeta['idempotent'] ?? false),
            'ttl_hours' => 24,
            'key_template' => '{event_id}:{record_id}:{method}:{endpoint}',
        ];
        $configPolicy = (array) ($httpConfig?->idempotency_config_json ?? []);
        $eventPolicy = (array) ($eventMeta['idempotency'] ?? []);

        if (array_key_exists('idempotent', $eventMeta) && ! array_key_exists('enabled', $eventPolicy)) {
            $eventPolicy['enabled'] = (bool) $eventMeta['idempotent'];
        }

        return array_merge($default, $configPolicy, $eventPolicy);
    }

    public function executeEndpointCall(array $payload, GenericHttpAdapter $httpAdapter): array
    {
        if (! $this->event) {
            throw new \RuntimeException('Generic platform event context is required for endpoint execution.');
        }

        return $httpAdapter->send(
            $this->platform->type ?? 'generic',
            $this->resolveEndpoint($this->event),
            $this->resolveMethod($this->event),
            $this->resolveHeaders($this->event, $this->platform),
            $this->resolveQueryParams($this->event, $payload),
            $this->resolveBody($this->event, $payload),
            $this->resolveTimeout($this->event),
            $this->resolveRetryPolicy($this->event)
        );
    }

    private function getHttpConfig(Event $event): ?EventHttpConfig
    {
        $event->loadMissing('httpConfig');

        $httpConfig = $event->httpConfig;
        if (! $httpConfig || ! $httpConfig->active) {
            return null;
        }

        return $httpConfig;
    }
}
