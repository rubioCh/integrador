<?php

namespace App\Listeners;

use App\Events\NextEvent;
use App\Jobs\ProcessNextEventJob;

class NextEventListener extends BaseListener
{
    public function handle(NextEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessNextEventJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
