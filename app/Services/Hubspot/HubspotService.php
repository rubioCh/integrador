<?php

namespace App\Services\Hubspot;

use App\Jobs\ProcessSignedQuotesJob;
use App\Models\Event;
use App\Models\PropertyRelationship;
use App\Models\Platform;
use App\Models\Record;
use App\Services\Base\BaseService;
use Illuminate\Support\Arr;

class HubspotService extends BaseService
{
    public function __construct(
        Platform $platform,
        ?Event $event = null,
        ?Record $record = null,
        protected ?HubspotApiServiceRefactored $hubspotApi = null,
        protected ?ProductCacheService $productCacheService = null
    ) {
        parent::__construct($platform, $event, $record);
        $this->applyPlatformConfiguration();

        $this->hubspotApi ??= app(HubspotApiServiceRefactored::class);
        $this->productCacheService ??= app(ProductCacheService::class);
    }

    public function companyCreatedWebhook(array $payload): array
    {
        return $this->success('Company created webhook processed.', [
            'company_id' => $payload['objectId'] ?? Arr::get($payload, 'company.id'),
            'payload' => $payload,
        ]);
    }

    public function contactCreatedWebhook(array $payload): array
    {
        return $this->success('Contact created webhook processed.', [
            'contact_id' => $payload['objectId'] ?? Arr::get($payload, 'contact.id'),
            'payload' => $payload,
        ]);
    }

    public function dealPropertyChange(string $subscriptionType, array $payload, $record): array
    {
        return $this->success('Deal property change received.', [
            'subscription_type' => $subscriptionType,
            'object_id' => $payload['objectId'] ?? null,
            'property_name' => $payload['propertyName'] ?? null,
            'property_value' => $payload['propertyValue'] ?? null,
        ]);
    }

    public function contactPropertyChange(string $subscriptionType, array $payload, $record): array
    {
        return $this->success('Contact property change received.', [
            'subscription_type' => $subscriptionType,
            'object_id' => $payload['objectId'] ?? null,
            'property_name' => $payload['propertyName'] ?? null,
            'property_value' => $payload['propertyValue'] ?? null,
        ]);
    }

    public function companyPropertyChange(string $subscriptionType, array $payload, $record): array
    {
        return $this->success('Company property change received.', [
            'subscription_type' => $subscriptionType,
            'object_id' => $payload['objectId'] ?? null,
            'property_name' => $payload['propertyName'] ?? null,
            'property_value' => $payload['propertyValue'] ?? null,
        ]);
    }

    public function objectPropertyChange(string $subscriptionType, array $payload, $record): array
    {
        return $this->success('Object property change received.', [
            'subscription_type' => $subscriptionType,
            'object_id' => $payload['objectId'] ?? null,
            'property_name' => $payload['propertyName'] ?? null,
            'property_value' => $payload['propertyValue'] ?? null,
        ]);
    }

    public function invoicePropertyChange(string $subscriptionType, array $payload, $record): array
    {
        return $this->success('Invoice property change received.', [
            'subscription_type' => $subscriptionType,
            'object_id' => $payload['objectId'] ?? null,
            'property_name' => $payload['propertyName'] ?? null,
            'property_value' => $payload['propertyValue'] ?? null,
        ]);
    }

    public function createProducts(array $products): array
    {
        if (empty($products)) {
            return $this->success('No products received for creation.', ['count' => 0]);
        }

        $created = [];
        $errors = [];

        foreach ($products as $index => $product) {
            $result = $this->hubspotApi->createProduct(is_array($product) ? $product : []);

            if (! $result['success']) {
                $errors[] = [
                    'index' => $index,
                    'error' => $result['error'] ?? $result['message'] ?? 'Unknown error',
                ];
                continue;
            }

            $created[] = $result['data'];
        }

        if (! empty($created)) {
            $this->productCacheService->preload($products);
        }

        return [
            'success' => empty($errors),
            'message' => empty($errors)
                ? 'Products created successfully in HubSpot.'
                : 'Some products failed to create in HubSpot.',
            'data' => [
                'created_count' => count($created),
                'error_count' => count($errors),
                'errors' => $errors,
            ],
        ];
    }

    public function updateProducts(array $updateProducts): array
    {
        if (empty($updateProducts)) {
            return $this->success('No products received for update.', ['count' => 0]);
        }

        $updated = [];
        $errors = [];

        foreach ($updateProducts as $index => $product) {
            if (! is_array($product)) {
                $errors[] = ['index' => $index, 'error' => 'Invalid product payload.'];
                continue;
            }

            $productId = (string) ($product['id'] ?? $product['hubspot_id'] ?? '');
            if ($productId === '') {
                $errors[] = ['index' => $index, 'error' => 'Missing HubSpot product id.'];
                continue;
            }

            $properties = $product;
            unset($properties['id'], $properties['hubspot_id']);

            $result = $this->hubspotApi->updateProduct($productId, $properties);
            if (! $result['success']) {
                $errors[] = [
                    'index' => $index,
                    'product_id' => $productId,
                    'error' => $result['error'] ?? $result['message'] ?? 'Unknown error',
                ];
                continue;
            }

            $updated[] = $result['data'];
        }

        if (! empty($updated)) {
            $this->productCacheService->preload($updateProducts);
        }

        return [
            'success' => empty($errors),
            'message' => empty($errors)
                ? 'Products updated successfully in HubSpot.'
                : 'Some products failed to update in HubSpot.',
            'data' => [
                'updated_count' => count($updated),
                'error_count' => count($errors),
                'errors' => $errors,
            ],
        ];
    }

    public function getSignedQuotes(): array
    {
        $quotes = $this->event?->meta['signed_quotes_sample']
            ?? $this->record?->payload['quotes']
            ?? null;

        if ($quotes === null) {
            $remote = $this->hubspotApi->searchSignedQuotes();
            if ($remote['success']) {
                $quotes = Arr::get($remote, 'data.results', []);
            }
        }

        if (! is_array($quotes) || empty($quotes)) {
            $quotes = [[
                'quote_id' => 'qs_' . now()->format('YmdHis'),
                'hubspot_quote_id' => 'hsq_' . now()->timestamp,
                'status' => 'signed',
                'entities' => [
                    'company' => [
                        'hubspot_id' => 'cmp_001',
                        'odoo_id' => null,
                        'fields' => [
                            'name' => 'Example Company',
                            'vat' => 'RFC-EXAMPLE-01',
                            'country' => 'MX',
                        ],
                        'sync_snapshots' => [
                            'odoo' => [],
                        ],
                    ],
                    'contact' => [
                        'hubspot_id' => 'ctc_001',
                        'odoo_id' => null,
                        'fields' => [
                            'firstname' => 'John',
                            'lastname' => 'Doe',
                            'email' => 'john.doe@example.com',
                        ],
                        'sync_snapshots' => [
                            'odoo' => [],
                        ],
                    ],
                    'products' => [[
                        'hubspot_id' => 'prd_001',
                        'odoo_id' => null,
                        'fields' => [
                            'name' => 'Subscription Starter',
                            'default_code' => 'SUB-START',
                            'price' => 99,
                        ],
                        'sync_snapshots' => [
                            'odoo' => [],
                        ],
                    ]],
                ],
            ]];
        }

        if ($this->record) {
            ProcessSignedQuotesJob::dispatch($quotes, $this->event, $this->record)->onQueue('signed-quotes');
        }

        return $this->success('Signed quotes queued.', [
            'count' => count($quotes),
            'quotes' => $quotes,
        ]);
    }

    public function getArchivedQuotes(): array
    {
        $response = $this->hubspotApi->request('GET', '/crm/v3/objects/quotes', [], [
            'archived' => true,
            'limit' => 100,
        ]);

        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to fetch archived quotes from HubSpot.',
                'data' => [
                    'error' => $response['error'] ?? null,
                ],
            ];
        }

        return $this->success('Archived quotes fetched from HubSpot.', [
            'count' => count(Arr::get($response, 'data.results', [])),
            'quotes' => Arr::get($response, 'data.results', []),
        ]);
    }

    public function createInvoice(): mixed
    {
        $payload = $this->resolvePayloadFromContext();
        $response = $this->hubspotApi->createInvoice($payload);

        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to create invoice in HubSpot.',
                'data' => [
                    'error' => $response['error'] ?? null,
                ],
            ];
        }

        return $this->success('Invoice created in HubSpot.', $response['data']);
    }

    public function createObject(): mixed
    {
        $payload = $this->resolvePayloadFromContext();
        $objectType = $this->resolveObjectType('companies');
        $response = $this->hubspotApi->createObject($objectType, $payload);

        if (! $response['success']) {
            $noteResult = $this->tryLogContactFailureNote(
                $objectType,
                (string) ($payload['id'] ?? $payload['hubspot_id'] ?? $payload['objectId'] ?? ''),
                'Failed to create contact in HubSpot.',
                $response
            );

            return [
                'success' => false,
                'message' => 'Failed to create object in HubSpot.',
                'data' => [
                    'error' => $response['error'] ?? null,
                    'hubspot_note' => $noteResult,
                ],
            ];
        }

        return $this->success('Object created in HubSpot.', $response['data']);
    }

    public function updateObject(): array
    {
        if (! $this->record) {
            return [
                'success' => false,
                'message' => 'Record context is required for HubSpot object update.',
                'data' => [],
            ];
        }

        $payload = $this->resolvePayloadFromContext();
        $objectType = $this->resolveObjectType('companies');
        $objectId = (string) ($payload['id'] ?? $payload['hubspot_id'] ?? '');

        if ($objectId === '') {
            return [
                'success' => false,
                'message' => 'Missing HubSpot object id for update.',
                'data' => [],
            ];
        }

        unset($payload['id'], $payload['hubspot_id']);

        $response = $this->hubspotApi->updateObject($objectType, $objectId, $payload);
        if (! $response['success']) {
            $noteResult = $this->tryLogContactFailureNote(
                $objectType,
                $objectId,
                'Failed to update contact in HubSpot.',
                $response
            );

            return [
                'success' => false,
                'message' => 'Failed to update object in HubSpot.',
                'data' => [
                    'error' => $response['error'] ?? null,
                    'hubspot_note' => $noteResult,
                ],
            ];
        }

        return $this->success('Object updated in HubSpot.', [
            'data' => $response['data'] ?? [],
        ]);
    }

    public function updateCompany(array $payload): array
    {
        $companyId = (string) ($payload['id'] ?? $payload['hubspot_id'] ?? '');
        if ($companyId === '') {
            return [
                'success' => false,
                'message' => 'Missing company id for HubSpot update.',
                'data' => [],
            ];
        }

        $properties = $payload;
        unset($properties['id'], $properties['hubspot_id']);

        $response = $this->hubspotApi->updateObject('companies', $companyId, $properties);
        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to update company in HubSpot.',
                'data' => ['error' => $response['error'] ?? null],
            ];
        }

        return $this->success('Company updated in HubSpot.', $response['data']);
    }

    public function syncContactExecutionResponse(array $payload): array
    {
        $contactId = $this->resolveHubspotContactIdFromPayload($payload);
        if ($contactId === null) {
            return [
                'success' => false,
                'message' => 'Missing HubSpot contact id for response write-back.',
                'data' => [
                    'required_context' => [
                        'hubspot_object_id',
                        'hubspot_contact_id',
                        'contact.id',
                        'contact.hubspot_id',
                        'objectId',
                        'id',
                    ],
                    'received_keys' => array_keys($payload),
                    'mapping_event_id' => $this->resolveResponseMappingEventId($payload),
                ],
            ];
        }

        $mappingEventId = $this->resolveResponseMappingEventId($payload);
        $properties = array_merge(
            $this->buildHubspotContactPropertiesFromResponse($mappingEventId, $payload),
            $this->buildPlatformSyncSuccessProperties($payload)
        );

        if ($properties === []) {
            return $this->success('No mapped HubSpot properties found in destination response.', [
                'contact_id' => $contactId,
                'mapping_event_id' => $mappingEventId,
                'destination_response_keys' => $this->extractDestinationResponseKeys($payload),
                'updated_properties' => [],
            ]);
        }

        $response = $this->hubspotApi->updateObject('contacts', $contactId, $properties);
        if (! $response['success']) {
            $noteResult = $this->tryLogContactFailureNote(
                'contacts',
                $contactId,
                'Failed to store destination response in HubSpot contact.',
                $response
            );

            return [
                'success' => false,
                'message' => 'Failed to update HubSpot contact from destination response.',
                'data' => [
                    'error' => $response['error'] ?? null,
                    'hubspot_note' => $noteResult,
                    'contact_id' => $contactId,
                    'mapping_event_id' => $mappingEventId,
                    'attempted_properties' => $properties,
                ],
            ];
        }

        return $this->success('HubSpot contact updated from destination response.', [
            'contact_id' => $contactId,
            'mapping_event_id' => $mappingEventId,
            'updated_properties' => $properties,
            'hubspot_response' => $response['data'] ?? [],
        ]);
    }

    public function testConnection(): array
    {
        $token = config('hubspot.access_token');
        if (! $token) {
            return [
                'success' => false,
                'message' => 'HubSpot access token is not configured.',
                'data' => [
                    'configured' => false,
                ],
            ];
        }

        $ping = $this->hubspotApi->ping();
        if (! $ping['success']) {
            return [
                'success' => false,
                'message' => 'HubSpot token configured but validation request failed.',
                'data' => [
                    'configured' => true,
                    'token_masked' => str_repeat('*', max(0, strlen($token) - 4)) . substr($token, -4),
                    'status_code' => $ping['status_code'] ?? 0,
                ],
            ];
        }

        return [
            'success' => true,
            'message' => 'HubSpot credentials validated.',
            'data' => [
                'configured' => true,
                'token_masked' => str_repeat('*', max(0, strlen($token) - 4)) . substr($token, -4),
                'account' => Arr::get($ping, 'data.portalId'),
            ],
        ];
    }

    private function success(string $message, array $data): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    private function resolvePayloadFromContext(): array
    {
        $payload = $this->record?->payload ?? [];

        if (isset($payload['payload']) && is_array($payload['payload'])) {
            return $payload['payload'];
        }

        return is_array($payload) ? $payload : [];
    }

    private function resolveObjectType(string $default): string
    {
        $metaObjectType = $this->event?->meta['object_type'] ?? null;
        if (is_string($metaObjectType) && trim($metaObjectType) !== '') {
            return trim($metaObjectType);
        }

        return $default;
    }

    private function applyPlatformConfiguration(): void
    {
        $credentials = $this->platform->credentials ?? [];
        $settings = $this->platform->settings ?? [];

        $overrides = [];

        $token = $credentials['access_token'] ?? $credentials['api_token'] ?? null;
        if (is_string($token) && trim($token) !== '') {
            $overrides['hubspot.access_token'] = $token;
        }

        $baseUrl = $settings['base_url'] ?? null;
        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            $overrides['hubspot.base_url'] = $baseUrl;
        }

        $timeout = $settings['timeout_seconds'] ?? null;
        if (is_numeric($timeout)) {
            $overrides['hubspot.timeout_seconds'] = (int) $timeout;
        }

        if (! empty($overrides)) {
            config($overrides);
        }
    }

    private function tryLogContactFailureNote(string $objectType, string $objectId, string $message, array $response): array
    {
        $normalizedType = strtolower(trim($objectType));
        if (! in_array($normalizedType, ['contact', 'contacts'], true)) {
            return [
                'attempted' => false,
                'reason' => 'object_type_not_contact',
            ];
        }

        if (trim($objectId) === '') {
            return [
                'attempted' => false,
                'reason' => 'contact_id_missing',
            ];
        }

        $noteResponse = $this->hubspotApi->addNoteToObject(
            'contacts',
            $objectId,
            $this->buildOperationalContactFailureNote($message, $response),
            [
            'event_id' => $this->event?->id,
            'record_id' => $this->record?->id,
            'error_message' => $response['message'] ?? null,
            ]
        );

        return [
            'attempted' => true,
            'success' => (bool) ($noteResponse['success'] ?? false),
            'contact_id' => $objectId,
            'note_id' => $noteResponse['data']['id'] ?? null,
            'status_code' => $noteResponse['status_code'] ?? null,
            'error' => $noteResponse['error'] ?? null,
        ];
    }

    private function resolveHubspotContactIdFromPayload(array $payload): ?string
    {
        $candidates = [
            Arr::get($payload, 'hubspot_contact_id'),
            Arr::get($payload, 'hubspot_object_id'),
            Arr::get($payload, 'contact.id'),
            Arr::get($payload, 'contact.hubspot_id'),
            Arr::get($payload, 'hubspot_id'),
            Arr::get($payload, 'objectId'),
            Arr::get($payload, 'id'),
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

    private function resolveResponseMappingEventId(array $payload): ?int
    {
        $candidate = $this->event?->meta['response_mapping_event_id']
            ?? Arr::get($payload, 'destination_execution.source_event_id')
            ?? Arr::get($payload, 'source_event_id');

        return is_numeric($candidate) ? (int) $candidate : null;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function buildHubspotContactPropertiesFromResponse(?int $mappingEventId, array $payload): array
    {
        if (! $mappingEventId) {
            return [];
        }

        $mappingEvent = Event::query()
            ->with(['propertyRelationships.property', 'propertyRelationships.relatedProperty'])
            ->find($mappingEventId);

        if (! $mappingEvent) {
            return [];
        }

        $properties = [];
        $responseData = Arr::get($payload, 'destination_response.data', []);
        $responseNestedData = Arr::get($responseData, 'data', []);

        $relationships = $mappingEvent->propertyRelationships
            ->filter(static fn (PropertyRelationship $relationship): bool => (bool) $relationship->active);

        foreach ($relationships as $relationship) {
            $hubspotKey = $relationship->property?->key ?: $relationship->property?->name;
            $targetKey = $relationship->relatedProperty?->key ?: $relationship->relatedProperty?->name;

            if (! is_string($hubspotKey) || trim($hubspotKey) === '' || ! is_string($targetKey) || trim($targetKey) === '') {
                continue;
            }

            $value = $this->firstMappedValue([
                Arr::get($responseData, $targetKey),
                Arr::get($responseNestedData, $targetKey),
            ]);

            if ($value === null) {
                continue;
            }

            $properties[$hubspotKey] = $this->normalizeHubspotPropertyValue($value);
        }

        return $properties;
    }

    private function firstMappedValue(array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function normalizeHubspotPropertyValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function buildOperationalContactFailureNote(string $message, array $response): string
    {
        $propertyName = $this->extractHubspotErrorPropertyName($response);
        $errorCode = $this->extractHubspotErrorCode($response);
        $category = Arr::get($response, 'error.category');

        return trim(implode("\n", array_filter([
            '[Integrador] Error de sincronizacion de contacto',
            'Operacion: ' . $this->resolveOperationalFailureLabel(),
            'Evento: ' . ($this->event?->name ?: $this->event?->event_type_id ?: 'N/A'),
            'Motivo: ' . trim($message),
            $propertyName ? 'Propiedad: ' . $propertyName : null,
            $errorCode ? 'Codigo: ' . $errorCode : null,
            is_string($category) && trim($category) !== '' ? 'Categoria: ' . $category : null,
            $this->record?->id ? 'Record: #' . $this->record->id : null,
            'Fecha: ' . now()->toISOString(),
        ])));
    }

    private function resolveOperationalFailureLabel(): string
    {
        $method = $this->event?->method_name;

        return match ($method) {
            'syncContactExecutionResponse' => 'write-back a HubSpot',
            'updateObject' => 'actualizacion de contacto en HubSpot',
            'createObject' => 'creacion de contacto en HubSpot',
            default => 'sincronizacion de contacto',
        };
    }

    private function extractHubspotErrorPropertyName(array $response): ?string
    {
        $contextProperty = Arr::get($response, 'error.errors.0.context.propertyName.0');
        if (is_scalar($contextProperty) && trim((string) $contextProperty) !== '') {
            return trim((string) $contextProperty);
        }

        return null;
    }

    private function extractHubspotErrorCode(array $response): ?string
    {
        $errorCode = Arr::get($response, 'error.errors.0.code')
            ?? Arr::get($response, 'error.code');

        if (! is_scalar($errorCode) || trim((string) $errorCode) === '') {
            return null;
        }

        return trim((string) $errorCode);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function buildPlatformSyncSuccessProperties(array $payload): array
    {
        $targetPlatform = $this->resolveTargetPlatformKey($payload);
        if ($targetPlatform === null) {
            return [];
        }

        $controlProperty = $this->resolvePlatformControlProperty($targetPlatform, $payload);

        return [
            $controlProperty => 'synced',
            'sync_status_' . $targetPlatform => 'success',
            'last_sync_' . $targetPlatform => now()->toISOString(),
            'last_error_' . $targetPlatform => '',
        ];
    }

    private function resolveTargetPlatformKey(array $payload): ?string
    {
        $candidates = [
            $this->event?->meta['target_platform'] ?? null,
            Arr::get($payload, 'target_platform'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            return strtolower(trim($candidate));
        }

        return null;
    }

    private function resolvePlatformControlProperty(string $targetPlatform, array $payload): string
    {
        $candidate = $this->event?->meta['control_property']
            ?? Arr::get($payload, 'control_property');

        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }

        return 'sync_to_' . $targetPlatform;
    }

    /**
     * @return list<string>
     */
    private function extractDestinationResponseKeys(array $payload): array
    {
        $responseData = Arr::get($payload, 'destination_response.data', []);
        if (! is_array($responseData)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $key): string => (string) $key,
            array_keys($responseData)
        ));
    }
}
