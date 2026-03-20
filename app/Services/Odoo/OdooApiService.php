<?php

namespace App\Services\Odoo;

use App\Services\RateLimitService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OdooApiService
{
    public function __construct(
        protected RateLimitService $rateLimitService
    ) {
    }

    public function authenticate(): array
    {
        $config = $this->config();
        $this->validateConfig($config);

        $response = $this->jsonRpcCall('/jsonrpc', [
            'service' => 'common',
            'method' => 'login',
            'args' => [
                $config['database'],
                $config['username'],
                $config['password'],
            ],
        ]);

        if (! $response['success']) {
            return $response;
        }

        return [
            'success' => true,
            'uid' => (int) ($response['data']['result'] ?? 0),
            'status_code' => $response['status_code'],
        ];
    }

    public function executeKw(string $model, string $method, array $args = [], array $kwargs = []): array
    {
        $config = $this->config();
        $this->validateConfig($config);

        $auth = $this->authenticate();
        if (! $auth['success']) {
            return $auth;
        }

        $payload = [
            'service' => 'object',
            'method' => 'execute_kw',
            'args' => [
                $config['database'],
                $auth['uid'],
                $config['password'],
                $model,
                $method,
                $args,
                (object) $kwargs,
            ],
        ];

        return $this->jsonRpcCall('/jsonrpc', $payload);
    }

    private function jsonRpcCall(string $path, array $params): array
    {
        $config = $this->config();
        $url = rtrim((string) $config['url'], '/') . '/' . ltrim($path, '/');

        $this->rateLimitService->throttle('odoo', $path);

        /** @var Response $response */
        $response = Http::timeout((int) $config['timeout_seconds'])
            ->acceptJson()
            ->post($url, [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => $params,
                'id' => uniqid('odoo_', true),
            ]);

        $decoded = $response->json() ?? [];

        if ($response->failed() || isset($decoded['error'])) {
            return [
                'success' => false,
                'status_code' => $response->status(),
                'message' => 'Odoo request failed.',
                'error' => $decoded['error'] ?? ['raw' => $response->body()],
            ];
        }

        return [
            'success' => true,
            'status_code' => $response->status(),
            'data' => $decoded,
        ];
    }

    private function config(): array
    {
        return [
            'url' => config('odoo.url'),
            'database' => config('odoo.database'),
            'username' => config('odoo.username'),
            'password' => config('odoo.password'),
            'timeout_seconds' => (int) config('odoo.timeout_seconds', 30),
        ];
    }

    private function validateConfig(array $config): void
    {
        foreach (['url', 'database', 'username', 'password'] as $key) {
            if (! isset($config[$key]) || $config[$key] === null || $config[$key] === '') {
                throw new \RuntimeException('Missing Odoo config key: ' . $key);
            }
        }
    }
}
