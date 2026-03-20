<?php

namespace App\Events\Invoice;

use App\Events\BaseEvent;

class CreateInvoiceEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'invoice.created';
    }

    public function getEventDescription(): string
    {
        return 'Invoice creation event';
    }
}
