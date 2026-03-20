<?php

namespace App\Listeners\Company;

use App\Events\Company\CreateCompanyEvent;
use App\Jobs\ProcessCompanyCreationJob;
use App\Listeners\BaseListener;

class CreateCompanyListener extends BaseListener
{
    public function handle(CreateCompanyEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessCompanyCreationJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
