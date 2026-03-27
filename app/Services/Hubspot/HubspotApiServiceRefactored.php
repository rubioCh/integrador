<?php

namespace App\Services\Hubspot;

use App\Services\RateLimitService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class HubspotApiServiceRefactored
{
    public function __construct(
        protected RateLimitService $rateLimitService
    ) {
    }

    public function ping(): array
    {
        return $this->request('GET', '/integrations/v1/me');
    }

    public function searchSignedQuotes(): array
    {
        $path = (string) config('hubspot.signed_quotes.search_path', '/crm/v3/objects/quotes/search');
        $statusProperty = (string) config('hubspot.signed_quotes.status_property', 'hs_status');
        $statusValue = (string) config('hubspot.signed_quotes.signed_status_value', 'SIGNED');

        return $this->request('POST', $path, [
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => $statusProperty,
                    'operator' => 'EQ',
                    'value' => $statusValue,
                ]],
            ]],
            'properties' => ['hs_title', 'hs_status', 'hs_quote_number'],
            'limit' => 100,
        ]);
    }

    public function createProduct(array $payload): array
    {
        return $this->request('POST', '/crm/v3/objects/products', [
            'properties' => $payload,
        ]);
    }

    public function updateProduct(string $productId, array $payload): array
    {
        return $this->request('PATCH', '/crm/v3/objects/products/' . $productId, [
            'properties' => $payload,
        ]);
    }

    public function createInvoice(array $payload): array
    {
        return $this->request('POST', '/crm/v3/objects/invoices', [
            'properties' => $payload,
        ]);
    }

    public function createObject(string $objectType, array $payload): array
    {
        return $this->request('POST', '/crm/v3/objects/' . $objectType, [
            'properties' => $payload,
        ]);
    }

    public function getObject(string $objectType, string $objectId, array $properties = []): array
    {
        $query = [];
        $requested = array_values(array_filter(array_map(
            static fn (mixed $property): string => is_scalar($property) ? trim((string) $property) : '',
            $properties
        )));

        if (! empty($requested)) {
            $query['properties'] = implode(',', array_unique($requested));
        }

        return $this->request('GET', '/crm/v3/objects/' . $objectType . '/' . $objectId, [], $query);
    }

    public function updateObject(string $objectType, string $objectId, array $payload): array
    {
        return $this->request('PATCH', '/crm/v3/objects/' . $objectType . '/' . $objectId, [
            'properties' => $payload,
        ]);
    }

    public function addNoteToObject(string $objectType, string $objectId, string $message, array $context = []): array
    {
        $normalizedType = $this->normalizeObjectType($objectType);
        $associationType = $this->resolveNoteAssociationType($normalizedType);
        $associationObjectType = $this->resolveAssociationObjectType($normalizedType);
        $recordToNoteAssociationType = $this->resolveRecordToNoteAssociationType($normalizedType);

        if ($objectId === '') {
            return $this->errorResponse(0, 'HubSpot object id is required to create note.');
        }

        if (! $associationType || ! $associationObjectType || ! $recordToNoteAssociationType) {
            return $this->errorResponse(0, 'HubSpot association type is not configured for note creation.', [
                'object_type' => $normalizedType,
            ]);
        }

        $contextLines = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $contextLines[] = sprintf('%s: %s', (string) $key, (string) $value);
            }
        }

        $noteBody = trim(implode("\n", array_filter([
            trim($message),
            empty($contextLines) ? null : 'Context: ' . implode(' | ', $contextLines),
        ])));

        $createResponse = $this->request('POST', '/crm/v3/objects/notes', [
            'properties' => [
                'hs_timestamp' => now()->toISOString(),
                'hs_note_body' => $noteBody,
            ],
            'associations' => [[
                'to' => [
                    'id' => (string) $objectId,
                ],
                'types' => [[
                    'associationCategory' => 'HUBSPOT_DEFINED',
                    'associationTypeId' => $associationType,
                ]],
            ]],
        ]);

        if (! ($createResponse['success'] ?? false)) {
            return $createResponse;
        }

        $noteId = (string) Arr::get($createResponse, 'data.id', '');
        if ($noteId === '') {
            return $createResponse;
        }

        $associationResponse = $this->request(
            'PUT',
            sprintf(
                '/crm/v3/objects/notes/%s/associations/%s/%s/%d',
                $noteId,
                $associationObjectType,
                (string) $objectId,
                $associationType
            )
        );

        if (! ($associationResponse['success'] ?? false)) {
            $createResponse['association'] = [
                'success' => false,
                'status_code' => $associationResponse['status_code'] ?? null,
                'error' => $associationResponse['error'] ?? null,
                'object_type' => $associationObjectType,
                'association_type_id' => $associationType,
            ];

            return $createResponse;
        }

        $timelineAssociationResponse = $this->request(
            'PUT',
            sprintf(
                '/crm/v3/objects/%s/%s/associations/notes/%s/%d',
                $associationObjectType,
                (string) $objectId,
                $noteId,
                $recordToNoteAssociationType
            )
        );

        if (! ($timelineAssociationResponse['success'] ?? false)) {
            $createResponse['timeline_association'] = [
                'success' => false,
                'status_code' => $timelineAssociationResponse['status_code'] ?? null,
                'error' => $timelineAssociationResponse['error'] ?? null,
                'object_type' => $associationObjectType,
                'association_type_id' => $recordToNoteAssociationType,
            ];

            return $createResponse;
        }

        $createResponse['association'] = [
            'success' => true,
            'status_code' => $associationResponse['status_code'] ?? null,
            'object_type' => $associationObjectType,
            'association_type_id' => $associationType,
        ];
        $createResponse['timeline_association'] = [
            'success' => true,
            'status_code' => $timelineAssociationResponse['status_code'] ?? null,
            'object_type' => $associationObjectType,
            'association_type_id' => $recordToNoteAssociationType,
        ];

        return $createResponse;
    }

    public function request(string $method, string $path, array $payload = [], array $query = []): array
    {
        $token = config('hubspot.access_token');
        if (! $token) {
            return $this->errorResponse(0, 'HubSpot access token is not configured.');
        }

        $baseUrl = rtrim((string) config('hubspot.base_url', 'https://api.hubapi.com'), '/');
        $url = $baseUrl . '/' . ltrim($path, '/');

        $this->rateLimitService->throttle('hubspot', $path);

        $request = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('hubspot.timeout_seconds', 30));

        /** @var Response $response */
        $response = $request->send(strtoupper($method), $url, [
            'query' => $query,
            'json' => $payload,
        ]);

        if ($response->failed()) {
            return $this->errorResponse(
                $response->status(),
                'HubSpot request failed.',
                $response->json() ?? ['raw' => $response->body()]
            );
        }

        return [
            'success' => true,
            'status_code' => $response->status(),
            'data' => $response->json() ?? [],
        ];
    }

    private function errorResponse(int $status, string $message, array $details = []): array
    {
        return [
            'success' => false,
            'status_code' => $status,
            'message' => $message,
            'error' => $details,
        ];
    }

    private function normalizeObjectType(string $objectType): string
    {
        return match (strtolower(trim($objectType))) {
            'contact', 'contacts' => 'contacts',
            'company', 'companies' => 'companies',
            'deal', 'deals' => 'deals',
            default => strtolower(trim($objectType)),
        };
    }

    private function resolveNoteAssociationType(string $objectType): ?int
    {
        $configured = Arr::get(config('hubspot.note_association_type_ids', []), $objectType);
        if (is_numeric($configured)) {
            return (int) $configured;
        }

        return match ($objectType) {
            'contacts' => 202,
            'companies' => 190,
            'deals' => 214,
            default => null,
        };
    }

    private function resolveAssociationObjectType(string $objectType): ?string
    {
        return match ($objectType) {
            'contacts' => 'contact',
            'companies' => 'company',
            'deals' => 'deal',
            default => null,
        };
    }

    private function resolveRecordToNoteAssociationType(string $objectType): ?int
    {
        return match ($objectType) {
            'contacts' => 201,
            'companies' => 190,
            'deals' => 213,
            default => null,
        };
    }
}
