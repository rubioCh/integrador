<?php

namespace App\Services\Aspel;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;
use App\Services\Generic\GenericHttpAdapter;
use App\Services\Generic\AuthStrategyResolver;
use App\Services\Generic\GenericPlatformService;
use Illuminate\Support\Arr;

class AspelService extends GenericPlatformService
{
    /**
     * @var list<string>
     */
    private const TECHNICAL_KEYS = [
        '_event_metadata',
        'destination_execution',
        'destination_response',
        'hubspotObjectId',
        'hubspot_contact_id',
        'hubspot_id',
        'hubspot_object',
        'hubspot_object_id',
        'hubspot_object_type',
        'hs_object_id',
        'last_error_aspel',
        'last_sync_aspel',
        'objectId',
        'portalId',
        'propertyName',
        'propertyValue',
        'source_event_id',
        'subscriptionType',
        'sync_status_aspel',
        'sync_to_aspel',
    ];

    public function __construct(
        Platform $platform,
        ?Event $event = null,
        ?Record $record = null,
        AuthStrategyResolver $authStrategyResolver,
        protected ?AspelApiService $aspelApiService = null
    ) {
        parent::__construct($platform, $event, $record, $authStrategyResolver);

        $this->aspelApiService ??= app(AspelApiService::class);
    }

    public function resolveBody(Event $event, array $payload): array
    {
        $body = parent::resolveBody($event, $payload);

        foreach (self::TECHNICAL_KEYS as $key) {
            Arr::forget($body, $key);
        }

        return array_filter(
            $body,
            static fn (mixed $value): bool => $value !== null
        );
    }

    public function syncContact(array $payload): array
    {
        return $this->upsertContact($payload);
    }

    public function upsertContact(array $payload, ?GenericHttpAdapter $httpAdapter = null): array
    {
        return $this->sendContactRequest('upsert', $payload, $httpAdapter);
    }

    public function createContact(array $payload, ?GenericHttpAdapter $httpAdapter = null): array
    {
        return $this->sendContactRequest('create', $payload, $httpAdapter);
    }

    public function updateContact(array $payload, ?GenericHttpAdapter $httpAdapter = null): array
    {
        $clave = $this->resolveAspelClave($payload);
        if ($clave === null) {
            return [
                'success' => false,
                'message' => 'ASPEL contact update requires clave.',
                'data' => [
                    'required_context' => ['clave'],
                    'received_keys' => array_keys($payload),
                ],
            ];
        }

        return $this->sendContactRequest('update', $payload, $httpAdapter, $clave);
    }

    public function getUpdatedContacts(array $payload = [], ?GenericHttpAdapter $httpAdapter = null): array
    {
        return $this->sendRequest(
            $this->resolveOperationEndpoint('poll', $payload),
            $this->resolveOperationHttpMethod('poll'),
            $payload,
            $httpAdapter
        );
    }

    public function executeEndpointCall(array $payload, GenericHttpAdapter $httpAdapter): array
    {
        $method = $this->resolveOperationMethod();

        if ($method && method_exists($this, $method)) {
            return $this->{$method}($payload, $httpAdapter);
        }

        return parent::executeEndpointCall($payload, $httpAdapter);
    }

    private function resolveOperationMethod(): ?string
    {
        $candidate = $this->event?->method_name
            ?? $this->event?->meta['operation']
            ?? null;

        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $value = trim($candidate);
        if (str_contains($value, '_')) {
            $value = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
        }

        return $value;
    }

    private function sendContactRequest(
        string $operation,
        array $payload,
        ?GenericHttpAdapter $httpAdapter = null,
        ?string $clave = null
    ): array {
        if (! $this->event) {
            return [
                'success' => false,
                'message' => 'ASPEL event context is required for contact sync.',
                'data' => [],
            ];
        }

        return $this->sendRequest(
            $this->resolveOperationEndpoint($operation, $payload, $clave),
            $this->resolveOperationHttpMethod($operation),
            $payload,
            $httpAdapter
        );
    }

    private function sendRequest(
        string $endpoint,
        string $method,
        array $payload,
        ?GenericHttpAdapter $httpAdapter = null
    ): array {
        return $this->aspelApiService->send(
            'aspel',
            $endpoint,
            $method,
            $this->resolveHeaders($this->event, $this->platform),
            $this->resolveQueryParams($this->event, $payload),
            $this->resolveBody($this->event, $payload),
            $this->resolveTimeout($this->event),
            $this->resolveRetryPolicy($this->event),
            $httpAdapter
        );
    }

    private function resolveOperationEndpoint(string $operation, array $payload, ?string $clave = null): string
    {
        $endpoint = $this->resolveEndpoint($this->event);

        return match ($operation) {
            'upsert' => $this->normalizeUpsertEndpoint($endpoint),
            'update' => $this->normalizeUpdateEndpoint($endpoint, $clave ?? $this->resolveAspelClave($payload)),
            default => $endpoint,
        };
    }

    private function resolveOperationHttpMethod(string $operation): string
    {
        return match ($operation) {
            'create', 'upsert' => 'POST',
            'update' => 'PUT',
            'poll' => 'GET',
            default => $this->resolveMethod($this->event),
        };
    }

    private function normalizeUpsertEndpoint(string $endpoint): string
    {
        if (preg_match('~/contacts/upsert/?$~i', $endpoint) === 1) {
            return $endpoint;
        }

        if (preg_match('~/contacts/?$~i', $endpoint) === 1) {
            return rtrim($endpoint, '/') . '/upsert';
        }

        return $endpoint;
    }

    private function normalizeUpdateEndpoint(string $endpoint, ?string $clave): string
    {
        if ($clave === null || trim($clave) === '') {
            return $endpoint;
        }

        $normalizedClave = trim($clave);

        if (str_contains($endpoint, '{clave}')) {
            return str_replace('{clave}', rawurlencode($normalizedClave), $endpoint);
        }

        if (preg_match('~/contacts/?$~i', $endpoint) === 1) {
            return rtrim($endpoint, '/') . '/' . rawurlencode($normalizedClave);
        }

        return $endpoint;
    }

    private function resolveAspelClave(array $payload): ?string
    {
        $candidates = [
            Arr::get($payload, 'clave'),
            Arr::get($payload, 'CLAVE'),
            Arr::get($payload, 'destination_response.data.clave'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
