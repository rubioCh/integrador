<?php

namespace App\Events\Company;

use App\Events\BaseEvent;

class UpdateCompanyEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'company.updated';
    }

    public function getEventDescription(): string
    {
        return 'Company update event';
    }
}
