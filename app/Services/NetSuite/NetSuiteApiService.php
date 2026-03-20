<?php

namespace App\Services\NetSuite;

use App\Services\RateLimitService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class NetSuiteApiService
{
    public function __construct(
        protected RateLimitService $rateLimitService
    ) {
    }

    public function ping(): array
    {
        return $this->request('GET', '/platform/v1/roles');
    }

    public function request(string $method, string $path, array $query = [], array $payload = []): array
    {
        $config = $this->config();
        $missing = $this->missingKeys($config);
        if (! empty($missing)) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'NetSuite credentials are incomplete.',
                'data' => ['missing' => $missing],
            ];
        }

        try {
            $url = rtrim($this->resolveBaseUrl($config), '/') . '/' . ltrim($path, '/');
            $authorization = $this->buildAuthorizationHeader(strtoupper($method), $url, $query, $config);

            $this->rateLimitService->throttle('netsuite', $path);

            /** @var Response $response */
            $response = Http::acceptJson()
                ->timeout((int) $config['timeout_seconds'])
                ->withHeaders([
                    'Authorization' => $authorization,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'transient',
                ])
                ->send(strtoupper($method), $url, [
                    'query' => $query,
                    'json' => $payload,
                ]);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'status_code' => 0,
                'message' => 'NetSuite request failed before network call.',
                'error' => [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
            ];
        }

        if ($response->failed()) {
            return [
                'success' => false,
                'status_code' => $response->status(),
                'message' => 'NetSuite request failed.',
                'error' => $response->json() ?? ['raw' => $response->body()],
            ];
        }

        return [
            'success' => true,
            'status_code' => $response->status(),
            'data' => $response->json() ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function missingKeys(array $config): array
    {
        $required = [
            'account',
            'consumer_key',
            'consumer_secret',
            'token_id',
            'token_secret',
        ];

        $missing = [];
        foreach ($required as $key) {
            if (! isset($config[$key]) || $config[$key] === null || $config[$key] === '') {
                $missing[] = 'NETSUITE_' . strtoupper($key);
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'account' => config('netsuite.account'),
            'consumer_key' => config('netsuite.consumer_key'),
            'consumer_secret' => config('netsuite.consumer_secret'),
            'token_id' => config('netsuite.token_id'),
            'token_secret' => config('netsuite.token_secret'),
            'base_url' => config('netsuite.base_url'),
            'timeout_seconds' => (int) config('netsuite.timeout_seconds', 30),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveBaseUrl(array $config): string
    {
        if (is_string($config['base_url'] ?? null) && $config['base_url'] !== '') {
            return (string) $config['base_url'];
        }

        return 'https://' . $config['account'] . '.suitetalk.api.netsuite.com/services/rest';
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $config
     */
    private function buildAuthorizationHeader(string $method, string $url, array $query, array $config): string
    {
        $oauth = [
            'oauth_consumer_key' => (string) $config['consumer_key'],
            'oauth_token' => (string) $config['token_id'],
            'oauth_nonce' => bin2hex(random_bytes(10)),
            'oauth_timestamp' => (string) time(),
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_version' => '1.0',
        ];

        $signature = $this->buildSignature($method, $url, $query, $oauth, (string) $config['consumer_secret'], (string) $config['token_secret']);
        $oauth['oauth_signature'] = $signature;

        $headerParts = ['realm="' . rawurlencode((string) $config['account']) . '"'];
        foreach ($oauth as $key => $value) {
            $headerParts[] = $key . '="' . rawurlencode((string) $value) . '"';
        }

        return 'OAuth ' . implode(', ', $headerParts);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $oauth
     */
    private function buildSignature(
        string $method,
        string $url,
        array $query,
        array $oauth,
        string $consumerSecret,
        string $tokenSecret
    ): string {
        $baseUrl = $this->normalizeUrl($url);
        $params = array_merge($query, $oauth);
        ksort($params);

        $parameterString = collect($params)
            ->map(function ($value, $key): string {
                return rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
            })
            ->implode('&');

        $baseString = strtoupper($method) . '&' . rawurlencode($baseUrl) . '&' . rawurlencode($parameterString);
        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);

        return base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return strtolower($scheme) . '://' . strtolower($host) . $port . $path;
    }
}
