<?php

namespace App\Listeners\Odoo;

use App\Events\Odoo\GetListPricesEvent;
use App\Jobs\ProcessListPricesJob;
use App\Listeners\BaseListener;

class GetListPricesListener extends BaseListener
{
    public function handle(GetListPricesEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessListPricesJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
