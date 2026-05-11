<?php

namespace App\Services\Aspel;

use App\Jobs\ProcessObjectUpdateJob;
use App\Models\Config;
use App\Models\Event;
use App\Models\EventIdempotencyKey;
use App\Models\Platform;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\EventProcessingService;
use App\Services\Generic\GenericHttpAdapter;
use App\Services\Generic\AuthStrategyResolver;
use App\Services\Generic\GenericPlatformService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AspelService extends GenericPlatformService
{
    private const DEFAULT_CHANGES_TAKE = 200;
    private const DEFAULT_INITIAL_LOOKBACK_HOURS = 24;
    private const CHANGE_IDEMPOTENCY_TTL_HOURS = 24 * 30;
    private const CHANGE_IDEMPOTENCY_STALE_MINUTES = 15;

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
        AuthStrategyResolver $authStrategyResolver,
        ?Event $event = null,
        ?Record $record = null,
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
        if (! $this->event) {
            return [
                'success' => false,
                'message' => 'ASPEL schedule context is required for contact change polling.',
                'data' => [],
            ];
        }

        if (! $this->record) {
            return $this->sendRequest(
                $this->resolveOperationEndpoint('poll', []),
                $this->resolveOperationHttpMethod('poll'),
                $payload,
                $httpAdapter
            );
        }

        $take = max(1, (int) ($this->event->meta['take'] ?? Arr::get($payload, 'take', self::DEFAULT_CHANGES_TAKE)));
        $cursor = $this->loadCursorState();
        $runStartedAt = now()->toISOString();

        $metrics = [
            'take' => $take,
            'pages_processed' => 0,
            'items_seen' => 0,
            'items_processed' => 0,
            'items_skipped' => 0,
            'items_failed' => 0,
        ];

        $this->persistRunState([
            'last_run_started_at' => $runStartedAt,
            'last_run_status' => 'running',
            'last_error' => null,
        ]);

        $currentCursor = [
            'sinceTs' => $cursor['sinceTs'],
            'sinceClave' => $cursor['sinceClave'],
        ];

        try {
            do {
                $pageResponse = $this->fetchChangesPage($currentCursor, $take, $httpAdapter);
                if (! ($pageResponse['success'] ?? false)) {
                    return $this->failPollingRun(
                        'Failed to fetch changed contacts from ASPEL.',
                        $pageResponse,
                        $currentCursor,
                        $metrics
                    );
                }

                $items = Arr::get($pageResponse, 'data.items', []);
                if (! is_array($items)) {
                    return $this->failPollingRun(
                        'Invalid ASPEL changes payload: items must be an array.',
                        $pageResponse,
                        $currentCursor,
                        $metrics
                    );
                }

                $nextSinceTs = $this->normalizeCursorValue(Arr::get($pageResponse, 'data.nextSinceTs'));
                $nextSinceClave = $this->normalizeCursorValue(Arr::get($pageResponse, 'data.nextSinceClave'));
                $hasMore = (bool) Arr::get($pageResponse, 'data.hasMore', false);

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        return $this->failPollingRun(
                            'Invalid ASPEL changes payload: change item must be an array.',
                            $pageResponse,
                            $currentCursor,
                            $metrics
                        );
                    }

                    $metrics['items_seen']++;
                    $changeIdempotency = $this->acquireChangeIdempotency($item);

                    if (($changeIdempotency['skip'] ?? false) === true) {
                        $metrics['items_skipped']++;
                        continue;
                    }

                    $clave = $this->resolveAspelClave($item);
                    $detailResponse = $this->getContactDetailByClave((string) $clave, $httpAdapter);

                    if (! ($detailResponse['success'] ?? false)) {
                        $this->updateChangeIdempotencyStatus($changeIdempotency['model'] ?? null, 'failed', [
                            'reason' => 'detail_fetch_failed',
                            'error' => $detailResponse['error'] ?? null,
                            'status_code' => $detailResponse['status_code'] ?? null,
                        ]);

                        $metrics['items_failed']++;

                        return $this->failPollingRun(
                            'Failed to fetch ASPEL contact detail.',
                            [
                                'changes_response' => $pageResponse,
                                'detail_response' => $detailResponse,
                                'failed_item' => $item,
                            ],
                            $currentCursor,
                            $metrics
                        );
                    }

                    $normalizedPayload = $this->buildHubspotSyncPayload(
                        $item,
                        Arr::get($detailResponse, 'data', []),
                        $currentCursor,
                        [
                            'sinceTs' => $nextSinceTs,
                            'sinceClave' => $nextSinceClave,
                        ],
                        $hasMore
                    );

                    $syncResult = $this->processHubspotChangeSynchronously($normalizedPayload);
                    if (! ($syncResult['success'] ?? false)) {
                        $this->updateChangeIdempotencyStatus($changeIdempotency['model'] ?? null, 'failed', [
                            'reason' => 'hubspot_sync_failed',
                            'record_id' => $syncResult['record_id'] ?? null,
                            'message' => $syncResult['message'] ?? null,
                        ]);

                        $metrics['items_failed']++;

                        return $this->failPollingRun(
                            'Failed to sync ASPEL contact change to HubSpot.',
                            [
                                'changes_response' => $pageResponse,
                                'detail_response' => $detailResponse,
                                'failed_item' => $item,
                                'hubspot_sync' => $syncResult,
                            ],
                            $currentCursor,
                            $metrics
                        );
                    }

                    $this->updateChangeIdempotencyStatus($changeIdempotency['model'] ?? null, 'success', [
                        'clave' => $normalizedPayload['clave'] ?? null,
                        'version_sinc' => $normalizedPayload['versionSinc'] ?? null,
                        'record_id' => $syncResult['record_id'] ?? null,
                        'hubspot_contact_id' => Arr::get($syncResult, 'data.contact_id'),
                    ]);

                    $metrics['items_processed']++;
                }

                $metrics['pages_processed']++;
                $currentCursor = [
                    'sinceTs' => $nextSinceTs ?: $currentCursor['sinceTs'],
                    'sinceClave' => $nextSinceClave ?: $currentCursor['sinceClave'],
                ];

                $this->persistCursorState($currentCursor);
            } while ($hasMore);
        } catch (\Throwable $exception) {
            return $this->failPollingRun(
                'ASPEL contact change polling failed unexpectedly.',
                [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
                $currentCursor,
                $metrics
            );
        }

        $runFinishedAt = now()->toISOString();
        $this->persistRunState([
            'last_run_finished_at' => $runFinishedAt,
            'last_run_status' => 'success',
            'last_error' => null,
        ]);

        $data = [
            'cursor' => [
                'sinceTs' => $currentCursor['sinceTs'],
                'sinceClave' => $currentCursor['sinceClave'],
            ],
            'metrics' => $metrics,
            'last_run_started_at' => $runStartedAt,
            'last_run_finished_at' => $runFinishedAt,
        ];

        $this->mergeRecordDetails([
            'service_output' => $data,
            'cursor_state' => $data['cursor'],
            'polling_metrics' => $metrics,
        ]);

        return [
            'success' => true,
            'message' => 'ASPEL contact changes processed successfully.',
            'data' => $data,
        ];
    }

    public function getContactDetailByClave(string $clave, ?GenericHttpAdapter $httpAdapter = null): array
    {
        $normalizedClave = trim($clave);
        if ($normalizedClave === '') {
            return [
                'success' => false,
                'message' => 'ASPEL contact detail requires clave.',
                'data' => [],
                'error' => [
                    'code' => 'missing_clave',
                    'message' => 'ASPEL contact detail requires clave.',
                    'details' => null,
                ],
                'status_code' => 0,
            ];
        }

        return $this->sendRequest(
            $this->resolveDetailEndpoint($normalizedClave),
            'GET',
            [],
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
        $normalizedMethod = strtoupper($method);
        $body = in_array($normalizedMethod, ['GET', 'DELETE'], true)
            ? []
            : $this->resolveBody($this->event, $payload);

        return $this->aspelApiService->send(
            'aspel',
            $endpoint,
            $normalizedMethod,
            $this->resolveHeaders($this->event, $this->platform),
            $this->resolveQueryParams($this->event, $payload),
            $body,
            $this->resolveTimeout($this->event),
            $this->resolveRetryPolicy($this->event),
            $httpAdapter
        );
    }

    private function fetchChangesPage(array $cursor, int $take, ?GenericHttpAdapter $httpAdapter = null): array
    {
        return $this->sendRequest(
            $this->resolveOperationEndpoint('poll', []),
            $this->resolveOperationHttpMethod('poll'),
            [
                'query' => [
                    'sinceTs' => $cursor['sinceTs'],
                    'sinceClave' => $cursor['sinceClave'],
                    'take' => $take,
                ],
            ],
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

    private function resolveDetailEndpoint(string $clave): string
    {
        $configured = $this->event?->meta['detail_endpoint'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            $endpoint = trim($configured);
        } else {
            $endpoint = $this->resolveOperationEndpoint('poll', []);
            $endpoint = preg_replace('~/changes/?$~i', '', $endpoint) ?: $endpoint;
        }

        if (str_contains($endpoint, '{clave}')) {
            return str_replace('{clave}', rawurlencode($clave), $endpoint);
        }

        return rtrim($endpoint, '/') . '/' . rawurlencode($clave);
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

    private function resolveAspelVersionSinc(array $payload): ?string
    {
        $candidates = [
            Arr::get($payload, 'versionSinc'),
            Arr::get($payload, 'version_sinc'),
            Arr::get($payload, 'VERSION_SINC'),
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

    private function loadCursorState(): array
    {
        return [
            'sinceTs' => $this->normalizeCursorValue($this->getCursorValue('since_ts'))
                ?? now()->subHours(self::DEFAULT_INITIAL_LOOKBACK_HOURS)->toISOString(),
            'sinceClave' => $this->normalizeCursorValue($this->getCursorValue('since_clave')) ?? '',
        ];
    }

    private function persistCursorState(array $cursor): void
    {
        $this->setCursorValue('since_ts', $cursor['sinceTs'] ?? null, 'ASPEL contacts sync cursor timestamp');
        $this->setCursorValue('since_clave', $cursor['sinceClave'] ?? '', 'ASPEL contacts sync cursor clave');
    }

    private function persistRunState(array $state): void
    {
        if (array_key_exists('last_run_started_at', $state)) {
            $this->setCursorValue('last_run_started_at', $state['last_run_started_at'], 'ASPEL contacts sync last run start');
        }

        if (array_key_exists('last_run_finished_at', $state)) {
            $this->setCursorValue('last_run_finished_at', $state['last_run_finished_at'], 'ASPEL contacts sync last run finish');
        }

        if (array_key_exists('last_run_status', $state)) {
            $this->setCursorValue('last_run_status', $state['last_run_status'], 'ASPEL contacts sync last run status');
        }

        if (array_key_exists('last_error', $state)) {
            $this->setCursorValue('last_error', $state['last_error'], 'ASPEL contacts sync last error');
        }
    }

    private function getCursorValue(string $suffix): mixed
    {
        $config = Config::query()
            ->where('key', $this->cursorConfigKey($suffix))
            ->first();

        if (! $config) {
            return null;
        }

        $value = $config->value;

        if (! is_array($value)) {
            return $value;
        }

        return $value['value'] ?? $value;
    }

    private function setCursorValue(string $suffix, mixed $value, ?string $description = null): void
    {
        Config::query()->updateOrCreate(
            ['key' => $this->cursorConfigKey($suffix)],
            [
                'value' => ['value' => $value],
                'description' => $description,
                'is_encrypted' => false,
            ]
        );
    }

    private function cursorConfigKey(string $suffix): string
    {
        return 'aspel.contacts.cursor.' . $this->event->id . '.' . $suffix;
    }

    private function normalizeCursorValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function acquireChangeIdempotency(array $item): array
    {
        $clave = $this->resolveAspelClave($item);
        $versionSinc = $this->resolveAspelVersionSinc($item);

        if ($clave === null || $versionSinc === null) {
            return [
                'skip' => false,
                'model' => null,
            ];
        }

        $key = $this->buildChangeIdempotencyKey($clave, $versionSinc);
        $expiresAt = now()->addHours(self::CHANGE_IDEMPOTENCY_TTL_HOURS);
        $staleBefore = now()->subMinutes(self::CHANGE_IDEMPOTENCY_STALE_MINUTES);

        $state = DB::transaction(function () use ($key, $expiresAt, $staleBefore, $clave, $versionSinc): array {
            $existing = EventIdempotencyKey::query()
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                $created = EventIdempotencyKey::query()->create([
                    'idempotency_key' => $key,
                    'event_id' => $this->event->id,
                    'record_id' => $this->record->id,
                    'endpoint' => 'aspel.contacts.changes',
                    'method' => 'CHANGE',
                    'status' => 'processing',
                    'expires_at' => $expiresAt,
                    'metadata' => [
                        'source' => 'aspel_contacts_changes',
                        'clave' => $clave,
                        'versionSinc' => $versionSinc,
                    ],
                ]);

                return ['state' => 'acquired', 'model' => $created];
            }

            $isExpired = $existing->expires_at?->isPast() ?? false;
            $isStaleProcessing = $existing->status === 'processing'
                && (($existing->updated_at?->lt($staleBefore)) ?? false);

            if (! $isExpired && $existing->status === 'success') {
                return ['state' => 'already_processed', 'model' => $existing];
            }

            if (! $isExpired && $existing->status === 'processing' && ! $isStaleProcessing) {
                return ['state' => 'already_processing', 'model' => $existing];
            }

            $metadata = is_array($existing->metadata) ? $existing->metadata : [];
            $existing->update([
                'event_id' => $this->event->id,
                'record_id' => $this->record->id,
                'endpoint' => 'aspel.contacts.changes',
                'method' => 'CHANGE',
                'status' => 'processing',
                'expires_at' => $expiresAt,
                'metadata' => array_merge($metadata, [
                    'source' => 'aspel_contacts_changes',
                    'clave' => $clave,
                    'versionSinc' => $versionSinc,
                ]),
            ]);

            return ['state' => 'acquired', 'model' => $existing->fresh()];
        });

        return [
            'skip' => in_array($state['state'], ['already_processed', 'already_processing'], true),
            'model' => $state['model'],
        ];
    }

    private function updateChangeIdempotencyStatus(?EventIdempotencyKey $model, string $status, array $metadata = []): void
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

    private function buildChangeIdempotencyKey(string $clave, string $versionSinc): string
    {
        return 'evt:' . $this->event->id . ':aspel-change:' . Str::lower(sha1($clave . '|' . $versionSinc));
    }

    private function buildHubspotSyncPayload(
        array $item,
        array $detail,
        array $currentCursor,
        array $nextCursor,
        bool $hasMore
    ): array {
        $phone = Arr::get($detail, 'phone') ?? Arr::get($detail, 'telefono');
        $email = Arr::get($detail, 'email') ?? Arr::get($detail, 'emailEnvio');

        return [
            'source_platform' => 'aspel',
            'source_event_id' => $this->event->id,
            'clave' => $this->resolveAspelClave($item),
            'rfc' => Arr::get($item, 'rfc') ?? Arr::get($detail, 'rfc'),
            'status' => Arr::get($item, 'status') ?? Arr::get($detail, 'status'),
            'versionSinc' => $this->resolveAspelVersionSinc($item),
            'phone' => $phone,
            'email' => $email,
            'aspel_detail' => $detail,
            'cursor_context' => [
                'input' => $currentCursor,
                'next' => $nextCursor,
                'hasMore' => $hasMore,
            ],
        ];
    }

    private function processHubspotChangeSynchronously(array $payload): array
    {
        $nextEvent = $this->event->to_event;
        if (! $nextEvent) {
            return [
                'success' => false,
                'message' => 'No HubSpot event configured for ASPEL contact changes.',
                'data' => [],
            ];
        }

        $record = app(EventLoggingService::class)->createEventRecord(
            $nextEvent->event_type_id ?? $nextEvent->name,
            'init',
            $payload,
            'ASPEL contact change ready for HubSpot sync.',
            $this->record->id,
            $nextEvent->id
        );

        try {
            (new ProcessObjectUpdateJob($nextEvent, $record, $payload))
                ->handle(app(EventProcessingService::class), app(EventLoggingService::class));
        } catch (\Throwable $exception) {
            $record->refresh();

            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'record_id' => $record->id,
                'data' => [
                    'status' => $record->status,
                    'details' => $record->details,
                ],
            ];
        }

        $record->refresh();

        if ($record->status !== 'success') {
            return [
                'success' => false,
                'message' => $record->message ?: 'HubSpot sync did not finish successfully.',
                'record_id' => $record->id,
                'data' => [
                    'status' => $record->status,
                    'details' => $record->details,
                ],
            ];
        }

        return [
            'success' => true,
            'message' => $record->message,
            'record_id' => $record->id,
            'data' => is_array($record->details) ? ($record->details['service_output'] ?? []) : [],
        ];
    }

    private function failPollingRun(string $message, array $context, array $cursor, array $metrics): array
    {
        $runFinishedAt = now()->toISOString();
        $this->persistRunState([
            'last_run_finished_at' => $runFinishedAt,
            'last_run_status' => 'error',
            'last_error' => $message,
        ]);

        $details = [
            'cursor_state' => $cursor,
            'polling_metrics' => $metrics,
            'failure_context' => $context,
            'last_run_finished_at' => $runFinishedAt,
        ];

        $this->mergeRecordDetails($details);

        return [
            'success' => false,
            'message' => $message,
            'data' => $details,
        ];
    }

    private function mergeRecordDetails(array $details): void
    {
        if (! $this->record) {
            return;
        }

        $existing = is_array($this->record->details) ? $this->record->details : [];
        $this->record->update([
            'details' => array_replace_recursive($existing, $details),
        ]);
    }
}
