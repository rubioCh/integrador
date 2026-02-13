<?php

namespace App\Listeners;

use App\Models\Record;
use App\Services\EventLoggingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

abstract class BaseListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'events';

    public function __construct(
        protected EventLoggingService $eventLoggingService
    ) {
    }

    protected function createRecord(
        string $eventType,
        array $payload,
        string $message,
        ?int $parentRecordId = null,
        ?int $eventId = null
    ): Record {
        return $this->eventLoggingService->createEventRecord(
            $eventType,
            'init',
            $payload,
            $message,
            $parentRecordId,
            $eventId,
        );
    }
}
