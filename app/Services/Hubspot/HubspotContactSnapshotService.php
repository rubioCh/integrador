<?php

namespace App\Services\Hubspot;

use App\Models\PlatformConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HubspotContactSnapshotService
{
    public function fetchContact(int|string $clientId, string $hubspotObjectId, array $properties): array
    {
        $connection = PlatformConnection::query()
            ->where('client_id', (int) $clientId)
            ->where('platform_type', 'hubspot')
            ->where('active', true)
            ->firstOrFail();

        $token = (string) ($connection->credentials['access_token'] ?? '');
        if ($token === '') {
            return $this->errorResponse(0, 'HubSpot access token is not configured for this client.');
        }

        $baseUrl = rtrim((string) ($connection->base_url ?: 'https://api.hubapi.com'), '/');
        $requestedProperties = array_values(array_filter(array_map(
            static fn (mixed $property): string => is_scalar($property) ? trim((string) $property) : '',
            $properties
        )));

        /** @var Response $response */
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) ($connection->settings['timeout_seconds'] ?? 30))
            ->get($baseUrl . '/crm/v3/objects/contacts/' . $hubspotObjectId, [
                'properties' => implode(',', array_unique($requestedProperties)),
            ]);

        if ($response->failed()) {
            return $this->errorResponse(
                $response->status(),
                'HubSpot contact snapshot request failed.',
                $response->json() ?? ['raw' => $response->body()]
            );
        }

        return [
            'success' => true,
            'status_code' => $response->status(),
            'data' => $response->json() ?? [],
        ];
    }

    public function addContactNote(PlatformConnection $connection, string $hubspotObjectId, string $message, array $context = []): array
    {
        $token = (string) ($connection->credentials['access_token'] ?? '');
        if ($token === '') {
            return $this->errorResponse(0, 'HubSpot access token is not configured for note creation.');
        }

        $baseUrl = rtrim((string) ($connection->base_url ?: 'https://api.hubapi.com'), '/');
        $noteBody = trim($message . (empty($context) ? '' : "\nContext: " . collect($context)
            ->filter(static fn (mixed $value): bool => is_scalar($value))
            ->map(static fn (mixed $value, string $key): string => $key . ': ' . $value)
            ->implode(' | ')));

        $payload = [
            'properties' => [
                'hs_timestamp' => now()->toISOString(),
                'hs_note_body' => $noteBody,
            ],
            'associations' => [[
                'to' => ['id' => $hubspotObjectId],
                'types' => [[
                    'associationCategory' => 'HUBSPOT_DEFINED',
                    'associationTypeId' => 202,
                ]],
            ]],
        ];

        /** @var Response $response */
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) ($connection->settings['timeout_seconds'] ?? 30))
            ->post($baseUrl . '/crm/v3/objects/notes', $payload);

        if ($response->failed()) {
            return $this->errorResponse(
                $response->status(),
                'HubSpot note creation failed.',
                $response->json() ?? ['raw' => $response->body()]
            );
        }

        return [
            'success' => true,
            'status_code' => $response->status(),
            'data' => $response->json() ?? [],
        ];
    }

    private function errorResponse(int $statusCode, string $message, array $error = []): array
    {
        return [
            'success' => false,
            'status_code' => $statusCode,
            'message' => $message,
            'error' => $error,
        ];
    }
}
