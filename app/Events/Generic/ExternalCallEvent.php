<?php

namespace App\Events\Generic;

use App\Events\BaseEvent;

class ExternalCallEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'generic.external.call';
    }

    public function getEventDescription(): string
    {
        return 'Generic external call event';
    }
}
