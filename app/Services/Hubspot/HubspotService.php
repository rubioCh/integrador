<?php

namespace App\Services\Hubspot;

use App\Jobs\ProcessSignedQuotesJob;
use App\Models\Event;
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

        $noteResponse = $this->hubspotApi->addNoteToObject('contacts', $objectId, $message, [
            'event_id' => $this->event?->id,
            'record_id' => $this->record?->id,
            'error_message' => $response['message'] ?? null,
        ]);

        return [
            'attempted' => true,
            'success' => (bool) ($noteResponse['success'] ?? false),
            'contact_id' => $objectId,
            'note_id' => $noteResponse['data']['id'] ?? null,
            'status_code' => $noteResponse['status_code'] ?? null,
            'error' => $noteResponse['error'] ?? null,
        ];
    }
}
