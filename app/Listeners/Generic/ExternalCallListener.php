<?php

namespace App\Listeners\Generic;

use App\Events\Generic\ExternalCallEvent;
use App\Jobs\Generic\EndpointExecutionJob;
use App\Listeners\BaseListener;

class ExternalCallListener extends BaseListener
{
    public function handle(ExternalCallEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        EndpointExecutionJob::dispatch($event->eventSchedule, $record, $event->data)
            ->onQueue('processing');
    }
}
