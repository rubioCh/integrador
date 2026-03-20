<?php

namespace App\Listeners\Quotes;

use App\Events\Quotes\SendingQuotesDataEvent;
use App\Jobs\ProcessQuoteDataJob;
use App\Listeners\BaseListener;

class SendingQuotesDataListener extends BaseListener
{
    public function handle(SendingQuotesDataEvent $event): void
    {
        $record = $this->createRecord(
            $event->getEventType(),
            $event->data,
            $event->getEventDescription(),
            $event->parentRecord->id ?? null,
            $event->eventSchedule->id ?? null
        );

        ProcessQuoteDataJob::dispatch($event->eventSchedule, $record, $event->data);
    }
}
