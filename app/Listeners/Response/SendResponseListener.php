<?php

namespace App\Listeners\Response;

use App\Events\Response\SendResponseEvent;
use App\Jobs\ProcessResponseJob;
use App\Listeners\BaseListener;

class SendResponseListener extends BaseListener
{
    public function handle(SendResponseEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessResponseJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
