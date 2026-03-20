<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SignedQuotesPipelineService
{
    /**
     * @param  array<int, array<string, mixed>>  $quotes
     * @return array<int, array<string, mixed>>
     */
    public function normalizeQuotes(array $quotes): array
    {
        $normalized = [];

        foreach ($quotes as $index => $quote) {
            if (! is_array($quote)) {
                continue;
            }

            $quoteId = (string) ($quote['quote_id'] ?? $quote['id'] ?? ('quote_' . ($index + 1)));
            $entities = Arr::get($quote, 'entities', []);

            $normalized[] = [
                'quote_id' => $quoteId,
                'hubspot_quote_id' => Arr::get($quote, 'hubspot_quote_id'),
                'status' => Arr::get($quote, 'status', 'signed'),
                'entities' => [
                    'company' => $this->normalizeEntity(Arr::get($entities, 'company', Arr::get($quote, 'company', []))),
                    'contact' => $this->normalizeEntity(Arr::get($entities, 'contact', Arr::get($quote, 'contact', []))),
                    'products' => $this->normalizeProducts(Arr::get($entities, 'products', Arr::get($quote, 'products', []))),
                ],
                'raw' => $quote,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $quotes
     * @return array<int, array<string, mixed>>
     */
    public function evaluateEntities(array $quotes, string $targetPlatform): array
    {
        return array_map(function (array $quote) use ($targetPlatform): array {
            $company = $this->evaluateEntity(
                Arr::get($quote, 'entities.company', []),
                $targetPlatform,
                'company'
            );
            $contact = $this->evaluateEntity(
                Arr::get($quote, 'entities.contact', []),
                $targetPlatform,
                'contact'
            );

            $products = [];
            foreach (Arr::get($quote, 'entities.products', []) as $product) {
                $products[] = $this->evaluateEntity($product, $targetPlatform, 'product');
            }

            return [
                'quote_id' => $quote['quote_id'],
                'hubspot_quote_id' => $quote['hubspot_quote_id'] ?? null,
                'status' => $quote['status'] ?? 'signed',
                'entity_actions' => [
                    'company' => $company,
                    'contact' => $contact,
                    'products' => $products,
                ],
                'raw' => $quote['raw'] ?? [],
            ];
        }, $quotes);
    }

    public function buildHubspotSyncMetadata(array $entityAction, string $targetPlatform): array
    {
        $operation = $entityAction['action'] ?? 'no_change';
        $updatedFields = $entityAction['changed_fields'] ?? [];

        return [
            'last_sync_' . $targetPlatform => now()->toISOString(),
            'sync_operation_' . $targetPlatform => $operation,
            'updated_fields_' . $targetPlatform => $updatedFields,
        ];
    }

    public function createExternalId(string $targetPlatform, string $entityType, string $reference): string
    {
        $suffix = Str::of($reference)->replaceMatches('/[^a-zA-Z0-9_\-]/', '')->lower();

        return sprintf('%s_%s_%s', $targetPlatform, $entityType, $suffix ?: Str::random(6));
    }

    private function normalizeEntity(mixed $entity): array
    {
        if (! is_array($entity)) {
            return [];
        }

        $fields = Arr::get($entity, 'fields', []);
        if (! is_array($fields)) {
            $fields = [];
        }

        return [
            'id' => Arr::get($entity, 'id'),
            'hubspot_id' => Arr::get($entity, 'hubspot_id'),
            'odoo_id' => Arr::get($entity, 'odoo_id'),
            'netsuite_id' => Arr::get($entity, 'netsuite_id'),
            'fields' => $fields,
            'sync_snapshots' => Arr::get($entity, 'sync_snapshots', []),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProducts(mixed $products): array
    {
        if (! is_array($products)) {
            return [];
        }

        $normalized = [];
        foreach ($products as $product) {
            $normalized[] = $this->normalizeEntity($product);
        }

        return $normalized;
    }

    private function evaluateEntity(array $entity, string $targetPlatform, string $entityType): array
    {
        $targetId = Arr::get($entity, $targetPlatform . '_id');
        $fields = Arr::get($entity, 'fields', []);
        if (! is_array($fields)) {
            $fields = [];
        }

        $snapshot = Arr::get($entity, 'sync_snapshots.' . $targetPlatform, []);
        if (! is_array($snapshot)) {
            $snapshot = [];
        }

        $changedFields = $this->detectChangedFields($fields, $snapshot);

        if (! $targetId) {
            $action = 'create';
        } elseif (! empty($changedFields)) {
            $action = 'update';
        } else {
            $action = 'no_change';
        }

        return [
            'entity_type' => $entityType,
            'entity' => $entity,
            'target_id' => $targetId,
            'action' => $action,
            'changed_fields' => $changedFields,
            'fields' => $fields,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $snapshot
     * @return array<int, string>
     */
    private function detectChangedFields(array $fields, array $snapshot): array
    {
        $changed = [];
        foreach ($fields as $key => $value) {
            $snapshotValue = $snapshot[$key] ?? null;
            if ($snapshotValue !== $value) {
                $changed[] = (string) $key;
            }
        }

        return $changed;
    }
}
