<?php

namespace App\Console\Commands;

use App\Services\Hubspot\ProductCacheService;
use Illuminate\Console\Command;

class ProductsCacheCommand extends Command
{
    protected $signature = 'products:cache {action : clear|preload|stats|validate}';

    protected $description = 'Manage HubSpot product cache';

    public function handle(ProductCacheService $productCacheService): int
    {
        $action = $this->argument('action');

        if ($action === 'clear') {
            $productCacheService->clear();
            $this->info('Product cache cleared.');
            return Command::SUCCESS;
        }

        if ($action === 'preload') {
            $productCacheService->preload();
            $this->info('Product cache preloaded.');
            return Command::SUCCESS;
        }

        if ($action === 'stats') {
            $stats = $productCacheService->stats();
            $this->line('Cached products: ' . ($stats['count'] ?? 0));
            $this->line('Updated at: ' . ($stats['updated_at'] ?? 'n/a'));
            return Command::SUCCESS;
        }

        if ($action === 'validate') {
            $result = $productCacheService->validate([]);
            $this->line('Checked products: ' . $result['total_checked']);
            $this->line('Missing: ' . count($result['missing']));
            $this->line('Stale: ' . count($result['stale']));
            $this->line('Resolved via selective checks: ' . count($result['resolved'] ?? []));
            return Command::SUCCESS;
        }

        $this->error('Invalid action.');
        return Command::FAILURE;
    }
}
