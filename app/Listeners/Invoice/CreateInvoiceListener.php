<?php

namespace App\Listeners\Invoice;

use App\Events\Invoice\CreateInvoiceEvent;
use App\Jobs\ProcessInvoiceCreationJob;
use App\Listeners\BaseListener;

class CreateInvoiceListener extends BaseListener
{
    public function handle(CreateInvoiceEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessInvoiceCreationJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
