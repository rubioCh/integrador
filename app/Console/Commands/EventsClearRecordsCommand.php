<?php

namespace App\Console\Commands;

use App\Services\EventCacheService;
use Illuminate\Console\Command;

class EventsClearRecordsCommand extends Command
{
    protected $signature = 'events:clear-records';

    protected $description = 'Clear event records history';

    public function handle(EventCacheService $eventCacheService): int
    {
        $count = $eventCacheService->clearRecords();
        $this->info('Event records cleared: ' . $count);

        return Command::SUCCESS;
    }
}
