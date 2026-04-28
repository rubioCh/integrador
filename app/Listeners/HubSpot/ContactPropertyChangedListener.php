<?php

namespace App\Listeners\HubSpot;

use App\Events\HubSpot\ContactPropertyChangedEvent;
use App\Jobs\HubSpot\ProcessContactPropertyChangeJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class ContactPropertyChangedListener implements ShouldQueue
{
    public string $queue = 'webhooks';

    public function handle(ContactPropertyChangedEvent $event): void
    {
        ProcessContactPropertyChangeJob::dispatch(
            $event->client,
            $event->connection,
            $event->payload
        )->onQueue('processing');
    }
}
