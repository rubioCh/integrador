<?php

namespace App\Events\Invoice;

use App\Events\BaseEvent;

class CreateRecurringInvoiceEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'invoice.recurring.created';
    }

    public function getEventDescription(): string
    {
        return 'Recurring invoice creation event';
    }
}
