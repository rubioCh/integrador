<?php

namespace App\Events\Object;

use App\Events\BaseEvent;

class UpdateObjectEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'object.updated';
    }

    public function getEventDescription(): string
    {
        return 'Object update event';
    }
}
