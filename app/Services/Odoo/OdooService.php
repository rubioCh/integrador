<?php

namespace App\Services\Odoo;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;
use App\Services\Base\BaseService;
use Illuminate\Support\Arr;

class OdooService extends BaseService
{
    public function __construct(
        Platform $platform,
        ?Event $event = null,
        ?Record $record = null,
        protected ?OdooApiService $odooApiService = null
    ) {
        parent::__construct($platform, $event, $record);
        $this->applyPlatformConfiguration();

        $this->odooApiService ??= app(OdooApiService::class);
    }

    public function resPartnerCreateCompany(array $payload): array
    {
        $response = $this->tryExecuteKw('res.partner', 'create', [[
            'name' => $payload['name'] ?? Arr::get($payload, 'company.name'),
            'email' => $payload['email'] ?? Arr::get($payload, 'company.email'),
            'phone' => $payload['phone'] ?? Arr::get($payload, 'company.phone'),
            'vat' => $payload['vat'] ?? Arr::get($payload, 'company.vat'),
            'is_company' => true,
        ]]);

        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Odoo company creation failed.',
                'data' => ['error' => $response['error'] ?? null],
            ];
        }

        return $this->success('Odoo company created.', [
            'id' => Arr::get($response, 'data.result'),
        ]);
    }

    public function resPartnerCreateContact(): void
    {
        if (! $this->record) {
            return;
        }

        $payload = $this->record->payload ?? [];
        $response = $this->tryExecuteKw('res.partner', 'create', [[
            'name' => Arr::get($payload, 'name') ?? trim((Arr::get($payload, 'firstname', '') . ' ' . Arr::get($payload, 'lastname', ''))),
            'email' => Arr::get($payload, 'email'),
            'phone' => Arr::get($payload, 'phone'),
            'type' => 'contact',
        ]]);

        $this->record->update([
            'status' => $response['success'] ? 'success' : 'error',
            'message' => $response['success']
                ? 'Odoo contact created.'
                : 'Odoo contact creation failed.',
            'details' => $response['success']
                ? ['id' => Arr::get($response, 'data.result')]
                : ['error' => $response['error'] ?? null],
        ]);
    }

    public function createUpdateContact(array $companyData, array $relatedPropertiesMap = [], string $contactType = 'company', ?int $parentId = null): int
    {
        return $parentId ?? 0;
    }

    public function syncCreateProducts(): array
    {
        $response = $this->tryExecuteKw(
            'product.template',
            'search_read',
            [[]],
            ['fields' => ['name', 'default_code', 'list_price'], 'limit' => 200]
        );

        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Odoo product sync (create) failed.',
                'data' => ['error' => $response['error'] ?? null],
            ];
        }

        return $this->success('Odoo products fetched for create sync.', [
            'products' => Arr::get($response, 'data.result', []),
        ]);
    }

    public function syncUpdateProducts(): array
    {
        return $this->syncCreateProducts();
    }

    public function createSaleOrder(array $data): array
    {
        $response = $this->tryExecuteKw('sale.order', 'create', [[
            'partner_id' => $data['partner_id'] ?? null,
            'origin' => $data['origin'] ?? null,
            'note' => $data['note'] ?? null,
        ]]);

        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Odoo sale order creation failed.',
                'data' => ['error' => $response['error'] ?? null],
            ];
        }

        return $this->success('Odoo sale order created.', [
            'id' => Arr::get($response, 'data.result'),
        ]);
    }

    public function createSaleSubscription(array $data): array
    {
        $response = $this->tryExecuteKw('sale.subscription', 'create', [[
            'partner_id' => $data['partner_id'] ?? null,
            'name' => $data['name'] ?? ('Subscription ' . now()->format('YmdHis')),
        ]]);

        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Odoo sale subscription creation failed.',
                'data' => ['error' => $response['error'] ?? null],
            ];
        }

        return $this->success('Odoo sale subscription created.', [
            'id' => Arr::get($response, 'data.result'),
        ]);
    }

    public function saleOrderCanceled(array $data): array
    {
        $orderId = $data['sale_order_id'] ?? $data['id'] ?? null;
        if (! $orderId) {
            return [
                'success' => false,
                'message' => 'Missing sale order id for cancel operation.',
                'data' => [],
            ];
        }

        $response = $this->tryExecuteKw('sale.order', 'action_cancel', [[(int) $orderId]]);

        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Odoo sale order cancel failed.',
                'data' => ['error' => $response['error'] ?? null],
            ];
        }

        return $this->success('Odoo sale order canceled.', [
            'sale_order_id' => (int) $orderId,
        ]);
    }

    public function accountMovePosted(string $subscriptionType, array $payload, $record): void
    {
        $record?->update([
            'status' => 'processing',
            'message' => 'Odoo account move posted received',
            'details' => ['subscription_type' => $subscriptionType],
        ]);
    }

    public function resPartnerUpdate(string $subscriptionType, array $payload, $record): void
    {
        $record?->update([
            'status' => 'processing',
            'message' => 'Odoo partner update received',
            'details' => ['subscription_type' => $subscriptionType],
        ]);
    }

    public function getListPricesByProduct(array $variant, $listPriceRecord): array
    {
        $productTemplateId = $variant['product_tmpl_id'] ?? $variant['template_id'] ?? null;
        if (! $productTemplateId) {
            return [
                'success' => false,
                'message' => 'Missing product template id for list price lookup.',
                'data' => [],
            ];
        }

        $response = $this->tryExecuteKw(
            'product.pricelist.item',
            'search_read',
            [[['product_tmpl_id', '=', (int) $productTemplateId]]],
            ['fields' => ['pricelist_id', 'fixed_price', 'min_quantity'], 'limit' => 100]
        );

        if (! $response['success']) {
            return [
                'success' => false,
                'message' => 'Odoo list price lookup failed.',
                'data' => ['error' => $response['error'] ?? null],
            ];
        }

        return $this->success('Odoo list prices fetched.', [
            'variant' => $variant,
            'list_prices' => Arr::get($response, 'data.result', []),
        ]);
    }

    public function testConnection(): array
    {
        $missing = [];
        if (! config('odoo.url')) {
            $missing[] = 'ODOO_URL';
        }
        if (! config('odoo.database')) {
            $missing[] = 'ODOO_DATABASE';
        }
        if (! config('odoo.username')) {
            $missing[] = 'ODOO_USERNAME';
        }
        if (! config('odoo.password')) {
            $missing[] = 'ODOO_PASSWORD';
        }

        if (! empty($missing)) {
            return [
                'success' => false,
                'message' => 'Odoo credentials are incomplete.',
                'data' => [
                    'configured' => false,
                    'missing' => $missing,
                ],
            ];
        }

        $auth = $this->tryAuthenticate();
        if (! $auth['success'] || ($auth['uid'] ?? 0) <= 0) {
            return [
                'success' => false,
                'message' => 'Odoo credentials configured but authentication failed.',
                'data' => [
                    'configured' => true,
                    'status_code' => $auth['status_code'] ?? 0,
                ],
            ];
        }

        return [
            'success' => true,
            'message' => 'Odoo credentials validated.',
            'data' => [
                'configured' => true,
                'uid' => $auth['uid'],
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

    private function tryExecuteKw(string $model, string $method, array $args = [], array $kwargs = []): array
    {
        try {
            return $this->odooApiService->executeKw($model, $method, $args, $kwargs);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Odoo request failed before execution.',
                'error' => [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }

    private function tryAuthenticate(): array
    {
        try {
            return $this->odooApiService->authenticate();
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'Odoo authentication failed before request.',
                'error' => [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }

    private function applyPlatformConfiguration(): void
    {
        $credentials = $this->platform->credentials ?? [];
        $settings = $this->platform->settings ?? [];

        $overrides = [];

        $url = $settings['url'] ?? $settings['base_url'] ?? null;
        if (is_string($url) && trim($url) !== '') {
            $overrides['odoo.url'] = $url;
        }

        $database = $credentials['database'] ?? null;
        if (is_string($database) && trim($database) !== '') {
            $overrides['odoo.database'] = $database;
        }

        $username = $credentials['username'] ?? null;
        if (is_string($username) && trim($username) !== '') {
            $overrides['odoo.username'] = $username;
        }

        $password = $credentials['password'] ?? null;
        if (is_string($password) && trim($password) !== '') {
            $overrides['odoo.password'] = $password;
        }

        $timeout = $settings['timeout_seconds'] ?? null;
        if (is_numeric($timeout)) {
            $overrides['odoo.timeout_seconds'] = (int) $timeout;
        }

        if (! empty($overrides)) {
            config($overrides);
        }
    }
}
