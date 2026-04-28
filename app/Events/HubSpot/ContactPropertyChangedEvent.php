<?php

namespace App\Events\HubSpot;

use App\Models\Client;
use App\Models\PlatformConnection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactPropertyChangedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Client $client,
        public PlatformConnection $connection,
        public array $payload
    ) {
    }
}
