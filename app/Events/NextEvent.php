<?php

namespace App\Events;

class NextEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'next.event';
    }

    public function getEventDescription(): string
    {
        return 'Next event dispatch';
    }
}
