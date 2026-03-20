<?php

namespace App\Listeners\Invoice;

use App\Events\Invoice\CreateRecurringInvoiceEvent;
use App\Jobs\ProcessRecurringInvoiceCreationJob;
use App\Listeners\BaseListener;

class CreateRecurringInvoiceListener extends BaseListener
{
    public function handle(CreateRecurringInvoiceEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessRecurringInvoiceCreationJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
