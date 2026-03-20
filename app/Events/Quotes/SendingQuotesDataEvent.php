<?php

namespace App\Events\Quotes;

use App\Events\BaseEvent;

class SendingQuotesDataEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'quotes.sending_data';
    }

    public function getEventDescription(): string
    {
        return 'Quotes data sending event';
    }
}
