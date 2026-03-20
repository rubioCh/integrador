<?php

namespace App\Listeners\SaleOrder;

use App\Events\SaleOrder\CreateSaleOrderEvent;
use App\Jobs\ProcessSaleOrderCreationJob;
use App\Listeners\BaseListener;

class CreateSaleOrderListener extends BaseListener
{
    public function handle(CreateSaleOrderEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessSaleOrderCreationJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
