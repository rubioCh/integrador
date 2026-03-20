<?php

namespace App\Console\Commands;

use App\Services\EventCacheService;
use Illuminate\Console\Command;

class EventsClearAllCommand extends Command
{
    protected $signature = 'events:clear-all';

    protected $description = 'Clear all event caches and records';

    public function handle(EventCacheService $eventCacheService): int
    {
        $count = $eventCacheService->clearAll();
        $this->info('Event cache cleared. Records deleted: ' . $count);

        return Command::SUCCESS;
    }
}
