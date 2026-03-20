<?php

namespace App\Listeners\Company;

use App\Events\Company\UpdateCompanyEvent;
use App\Jobs\ProcessCompanyUpdateJob;
use App\Listeners\BaseListener;

class UpdateCompanyListener extends BaseListener
{
    public function handle(UpdateCompanyEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessCompanyUpdateJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
