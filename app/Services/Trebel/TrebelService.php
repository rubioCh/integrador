<?php

namespace App\Services\Trebel;

use App\Models\PlatformConnection;
use App\Models\TrebelTemplate;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrebelService
{
    public function sendTemplate(PlatformConnection $connection, TrebelTemplate $template, array $contact, array $context = []): array
    {
        $method = strtoupper((string) ($connection->settings['http_method'] ?? 'POST'));
        $path = trim((string) ($connection->settings['send_path'] ?? ''));
        $baseUrl = rtrim((string) ($connection->base_url ?? ''), '/');

        if ($baseUrl === '' || $path === '') {
            return $this->errorResponse(0, 'Trebel connection is missing base_url or send_path.');
        }

        $headers = $this->resolveHeaders($connection);
        $payloadTemplate = $connection->settings['request_template'] ?? $template->payload_mapping ?? null;

        if (! is_array($payloadTemplate)) {
            return $this->errorResponse(0, 'Trebel request template is not configured.');
        }

        $payload = $this->interpolate($payloadTemplate, [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'external_template_id' => $template->external_template_id,
            ],
            'contact' => $contact,
            'context' => $context,
        ]);

        /** @var Response $response */
        $response = Http::withHeaders($headers)
            ->acceptJson()
            ->timeout((int) ($connection->settings['timeout_seconds'] ?? 20))
            ->send($method, $baseUrl . '/' . ltrim($path, '/'), [
                'json' => $payload,
            ]);

        if ($response->failed()) {
            return $this->errorResponse(
                $response->status(),
                'Trebel request failed.',
                $response->json() ?? ['raw' => $response->body()],
                $response
            );
        }

        return [
            'success' => true,
            'status_code' => $response->status(),
            'retryable' => false,
            'request_id' => $response->header('x-request-id') ?? $response->header('x-correlation-id'),
            'external_id' => Arr::get($response->json(), 'id')
                ?? Arr::get($response->json(), 'data.id')
                ?? Arr::get($response->json(), 'message_id'),
            'data' => $response->json() ?? [],
            'error' => [
                'code' => null,
                'message' => null,
                'details' => null,
            ],
        ];
    }

    private function resolveHeaders(PlatformConnection $connection): array
    {
        $headers = array_filter($connection->settings['headers'] ?? [], 'is_scalar');
        $authMode = (string) ($connection->settings['auth_mode'] ?? '');

        if ($authMode === 'bearer_api_key' && ! empty($connection->credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $connection->credentials['api_key'];
        }

        if ($authMode === 'header_api_key' && ! empty($connection->credentials['api_key'])) {
            $headerName = (string) ($connection->settings['api_key_header'] ?? 'X-API-Key');
            $headers[$headerName] = $connection->credentials['api_key'];
        }

        if ($authMode === 'basic_auth') {
            $user = (string) ($connection->credentials['username'] ?? '');
            $password = (string) ($connection->credentials['password'] ?? '');
            if ($user !== '' || $password !== '') {
                $headers['Authorization'] = 'Basic ' . base64_encode($user . ':' . $password);
            }
        }

        return array_merge(['Content-Type' => 'application/json'], $headers);
    }

    private function interpolate(mixed $value, array $context): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->interpolate($item, $context);
            }

            return $result;
        }

        if (! is_string($value)) {
            return $value;
        }

        return preg_replace_callback('/\{\{\s*([^}\s]+)\s*\}\}/', function (array $matches) use ($context): string {
            $resolved = Arr::get($context, $matches[1]);

            if (is_scalar($resolved) || $resolved === null) {
                return (string) ($resolved ?? '');
            }

            return Str::of(json_encode($resolved))->toString();
        }, $value) ?? $value;
    }

    private function errorResponse(int $statusCode, string $message, array $details = [], ?Response $response = null): array
    {
        return [
            'success' => false,
            'status_code' => $statusCode,
            'retryable' => in_array($statusCode, [0, 408, 409, 425, 429, 500, 502, 503, 504], true),
            'request_id' => $response?->header('x-request-id') ?? $response?->header('x-correlation-id'),
            'external_id' => null,
            'data' => [],
            'error' => [
                'code' => $statusCode > 0 ? (string) $statusCode : null,
                'message' => $message,
                'details' => $details,
            ],
        ];
    }
}
