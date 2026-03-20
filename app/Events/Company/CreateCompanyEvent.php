<?php

namespace App\Events\Company;

use App\Events\BaseEvent;

class CreateCompanyEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'company.created';
    }

    public function getEventDescription(): string
    {
        return 'Company creation event';
    }
}
