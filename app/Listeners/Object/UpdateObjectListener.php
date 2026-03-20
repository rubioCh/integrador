<?php

namespace App\Listeners\Object;

use App\Events\Object\UpdateObjectEvent;
use App\Jobs\ProcessObjectUpdateJob;
use App\Listeners\BaseListener;

class UpdateObjectListener extends BaseListener
{
    public function handle(UpdateObjectEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessObjectUpdateJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
