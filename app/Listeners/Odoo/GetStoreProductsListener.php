<?php

namespace App\Listeners\Odoo;

use App\Events\Odoo\GetStoreProductsEvent;
use App\Jobs\ProcessStoreProductsJob;
use App\Listeners\BaseListener;

class GetStoreProductsListener extends BaseListener
{
    public function handle(GetStoreProductsEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessStoreProductsJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
