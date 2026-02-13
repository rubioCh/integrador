<?php

namespace App\Events;

use App\Models\Event;
use App\Models\Record;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class BaseEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Event $eventSchedule,
        public Record $parentRecord,
        public array $data
    ) {
    }

    abstract public function getEventType(): string;

    abstract public function getEventDescription(): string;
}
