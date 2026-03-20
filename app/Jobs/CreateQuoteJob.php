<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Record;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class CreateQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $backoff = 300;
    public int $timeout = 900;

    public function __construct(
        public array $payload,
        public Event $event,
        public Record $record
    ) {
        $this->onQueue('signed-quotes');
    }

    public function handle(): void
    {
        $this->record->update([
            'status' => 'processing',
            'message' => 'Creating quote in target platform',
        ]);

        $createdQuotes = [];
        foreach (Arr::get($this->payload, 'quotes', []) as $quote) {
            $createdQuotes[] = [
                'quote_id' => Arr::get($quote, 'quote_id'),
                'hubspot_quote_id' => Arr::get($quote, 'hubspot_quote_id'),
                'target_platform' => Arr::get($this->payload, 'target_platform', 'odoo'),
                'entity_results' => Arr::get($quote, 'entity_results', []),
                'hubspot_sync_metadata' => Arr::get($quote, 'hubspot_sync_metadata', []),
                'status' => 'created',
            ];
        }

        $this->record->update([
            'status' => 'success',
            'message' => 'Quote creation completed',
            'details' => [
                'target_platform' => Arr::get($this->payload, 'target_platform', 'odoo'),
                'summary' => Arr::get($this->payload, 'summary', []),
                'quotes' => $createdQuotes,
            ],
        ]);
    }
}
