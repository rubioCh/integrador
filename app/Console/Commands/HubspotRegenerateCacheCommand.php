<?php

namespace App\Console\Commands;

use App\Services\Hubspot\ProductCacheService;
use Illuminate\Console\Command;

class HubspotRegenerateCacheCommand extends Command
{
    protected $signature = 'hubspot:regenerate-cache';

    protected $description = 'Regenerate HubSpot product cache';

    public function handle(ProductCacheService $productCacheService): int
    {
        $productCacheService->clear();
        $productCacheService->preload();
        $this->info('HubSpot cache regenerated.');

        return Command::SUCCESS;
    }
}
