<?php

namespace App\Listeners\Product;

use App\Events\Product\CreateProductEvent;
use App\Jobs\ProcessProductCreationJob;
use App\Listeners\BaseListener;

class CreateProductListener extends BaseListener
{
    public function handle(CreateProductEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessProductCreationJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
