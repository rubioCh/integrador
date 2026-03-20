<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitService
{
    public function throttle(string $platform, string $endpoint): array
    {
        $rps = $this->getPlatformRps($platform);
        if (! $rps || $rps <= 0) {
            return [
                'delay_ms' => 0,
                'rps' => $rps,
            ];
        }

        $minInterval = 1 / $rps;
        $key = $this->getRateLimitKey($platform, $endpoint);
        $now = microtime(true);

        $last = Cache::get($key);
        if ($last !== null) {
            $elapsed = $now - (float) $last;
            if ($elapsed < $minInterval) {
                $sleepSeconds = $minInterval - $elapsed;
                usleep((int) round($sleepSeconds * 1_000_000));
                $now = microtime(true);
            }
        }

        Cache::put($key, $now, 60);

        return [
            'delay_ms' => $last === null ? 0 : (int) max(0, round(($minInterval - ($elapsed ?? 0)) * 1000)),
            'rps' => $rps,
        ];
    }

    public function computeBackoffMs(int $attempt, ?int $retryAfterSeconds = null): int
    {
        $config = config('rate-limits.backoff');
        $base = (int) ($config['base_ms'] ?? 250);
        $max = (int) ($config['max_ms'] ?? 5000);
        $jitter = (bool) ($config['jitter'] ?? true);

        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            $backoff = $retryAfterSeconds * 1000;
        } else {
            $backoff = $base * (2 ** max(0, $attempt - 1));
        }

        if ($jitter) {
            $backoff = (int) round($backoff * mt_rand(80, 120) / 100);
        }

        return min($backoff, $max);
    }

    public function logBackoff(string $platform, string $endpoint, int $statusCode, ?int $retryAfterSeconds, int $attempt, int $backoffMs): void
    {
        Log::warning('Rate limit backoff triggered', [
            'platform' => $platform,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'retry_after' => $retryAfterSeconds,
            'attempt' => $attempt,
            'backoff_ms' => $backoffMs,
        ]);
    }

    private function getPlatformRps(string $platform): ?float
    {
        $platforms = config('rate-limits.platforms', []);
        $rps = $platforms[$platform] ?? null;

        if ($rps === null || $rps === '') {
            return null;
        }

        return (float) $rps;
    }

    private function getRateLimitKey(string $platform, string $endpoint): string
    {
        $endpointKey = trim($endpoint) !== '' ? $endpoint : 'default';

        return sprintf('rate_limit:%s:%s', $platform, md5($endpointKey));
    }
}
