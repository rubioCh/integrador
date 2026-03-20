<?php

namespace App\Events\Response;

use App\Events\BaseEvent;

class SendResponseEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'response.send';
    }

    public function getEventDescription(): string
    {
        return 'Response sending event';
    }
}
