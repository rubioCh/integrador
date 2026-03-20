<?php

namespace App\Jobs\Odoo;

use App\Jobs\Concerns\ProcessesEventJob;
use App\Models\Event;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\EventProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class OdooGetListPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    use ProcessesEventJob;
    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        public Event $event,
        public Record $record,
        public array $payload
    ) {
        $this->onQueue('sync');
    }

    public function handle(EventProcessingService $eventProcessingService, EventLoggingService $eventLoggingService): void
    {
        $this->processEvent(
            $eventProcessingService,
            $eventLoggingService,
            $this->event,
            $this->record,
            $this->payload,
            'Fetching Odoo list prices'
        );
    }
}
