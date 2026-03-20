<?php

namespace App\Console\Commands;

use App\Services\EventCacheService;
use Illuminate\Console\Command;

class EventsClearCacheCommand extends Command
{
    protected $signature = 'events:clear-cache';

    protected $description = 'Clear cached event data';

    public function handle(EventCacheService $eventCacheService): int
    {
        $eventCacheService->clearCache();
        $this->info('Event cache cleared.');

        return Command::SUCCESS;
    }
}
