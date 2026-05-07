<?php

namespace App\Providers;

use App\Events\HubSpot\ContactPropertyChangedEvent;
use App\Listeners\HubSpot\ContactPropertyChangedListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ContactPropertyChangedEvent::class => [
            ContactPropertyChangedListener::class,
        ],
    ];

    /**
     * Disable auto-discovery to avoid duplicate listener registration.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
