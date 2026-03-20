<?php

namespace App\Listeners\Product;

use App\Events\Product\UpdateProductEvent;
use App\Jobs\ProcessProductUpdateJob;
use App\Listeners\BaseListener;

class UpdateProductListener extends BaseListener
{
    public function handle(UpdateProductEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessProductUpdateJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
