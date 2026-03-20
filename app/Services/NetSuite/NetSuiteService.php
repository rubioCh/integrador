<?php

namespace App\Services\NetSuite;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;
use App\Services\Base\BaseService;

class NetSuiteService extends BaseService
{
    public function __construct(
        Platform $platform,
        ?Event $event = null,
        ?Record $record = null,
        protected ?NetSuiteApiService $netSuiteApiService = null
    ) {
        parent::__construct($platform, $event, $record);
        $this->applyPlatformConfiguration();

        $this->netSuiteApiService ??= app(NetSuiteApiService::class);
    }

    public function testConnection(): array
    {
        $config = $this->netSuiteApiService->config();
        $missing = $this->netSuiteApiService->missingKeys($config);

        if (! empty($missing)) {
            return [
                'success' => false,
                'message' => 'NetSuite credentials are incomplete.',
                'data' => [
                    'configured' => false,
                    'missing' => $missing,
                ],
            ];
        }

        $ping = $this->netSuiteApiService->ping();
        if (! $ping['success']) {
            return [
                'success' => false,
                'message' => 'NetSuite credentials configured but ping failed.',
                'data' => [
                    'configured' => true,
                    'status_code' => $ping['status_code'] ?? 0,
                ],
            ];
        }

        return [
            'success' => true,
            'message' => 'NetSuite credentials validated.',
            'data' => [
                'configured' => true,
                'status_code' => $ping['status_code'] ?? 200,
            ],
        ];
    }

    private function applyPlatformConfiguration(): void
    {
        $credentials = $this->platform->credentials ?? [];
        $settings = $this->platform->settings ?? [];

        $mapping = [
            'account' => 'netsuite.account',
            'consumer_key' => 'netsuite.consumer_key',
            'consumer_secret' => 'netsuite.consumer_secret',
            'token_id' => 'netsuite.token_id',
            'token_secret' => 'netsuite.token_secret',
            'private_key' => 'netsuite.private_key',
        ];

        $overrides = [];
        foreach ($mapping as $credentialKey => $configKey) {
            $value = $credentials[$credentialKey] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $overrides[$configKey] = $value;
            }
        }

        $baseUrl = $settings['base_url'] ?? null;
        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            $overrides['netsuite.base_url'] = $baseUrl;
        }

        $timeout = $settings['timeout_seconds'] ?? null;
        if (is_numeric($timeout)) {
            $overrides['netsuite.timeout_seconds'] = (int) $timeout;
        }

        if (! empty($overrides)) {
            config($overrides);
        }
    }
}
