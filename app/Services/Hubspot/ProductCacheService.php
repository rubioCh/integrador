<?php

namespace App\Services\Hubspot;

use Illuminate\Support\Facades\Cache;

class ProductCacheService
{
    private const CACHE_KEY = 'hubspot:products:cache';
    private const CACHE_META_KEY = 'hubspot:products:cache:meta';

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_META_KEY);
    }

    public function preload(array $products = []): array
    {
        $cache = $this->normalizeProducts($products);
        Cache::put(self::CACHE_KEY, $cache, now()->addHours(2));
        Cache::put(self::CACHE_META_KEY, [
            'count' => count($cache),
            'updated_at' => now()->toISOString(),
        ], now()->addHours(2));

        return $cache;
    }

    public function stats(): array
    {
        $cache = Cache::get(self::CACHE_KEY, []);
        $meta = Cache::get(self::CACHE_META_KEY, []);

        return [
            'count' => $meta['count'] ?? (is_array($cache) ? count($cache) : 0),
            'updated_at' => $meta['updated_at'] ?? null,
        ];
    }

    public function validate(array $products, ?callable $resolver = null): array
    {
        $cache = Cache::get(self::CACHE_KEY, []);
        $incoming = $this->normalizeProducts($products);

        $missing = array_diff_key($incoming, $cache);
        $stale = [];
        $resolved = [];

        foreach ($incoming as $key => $payload) {
            if (! isset($cache[$key])) {
                if ($resolver !== null) {
                    // 100ms delay between selective external checks (spec requirement).
                    usleep(100_000);
                    $resolved[$key] = $resolver($payload, $key);
                }
                continue;
            }
            if ($cache[$key] !== $payload) {
                $stale[$key] = [
                    'cached' => $cache[$key],
                    'incoming' => $payload,
                ];
            }
        }

        return [
            'missing' => $missing,
            'stale' => $stale,
            'total_checked' => count($incoming),
            'resolved' => $resolved,
        ];
    }

    private function normalizeProducts(array $products): array
    {
        $normalized = [];
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }
            $key = $product['default_code']
                ?? $product['name']
                ?? $product['display_name']
                ?? $product['platform_id']
                ?? null;

            if (! $key) {
                continue;
            }

            $normalized[(string) $key] = $product;
        }

        return $normalized;
    }
}
