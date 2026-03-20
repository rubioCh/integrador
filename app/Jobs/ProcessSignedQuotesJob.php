<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\SignedQuotesPipelineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class ProcessSignedQuotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $backoff = 300;
    public int $timeout = 900;

    public function __construct(
        public array $quotes,
        public Event $event,
        public Record $record
    ) {
        $this->onQueue('signed-quotes');
    }

    public function handle(EventLoggingService $eventLoggingService, SignedQuotesPipelineService $pipelineService): void
    {
        $this->record->update([
            'status' => 'processing',
            'message' => 'Processing signed quotes',
        ]);

        $normalizedQuotes = $pipelineService->normalizeQuotes($this->quotes);

        $validationRecord = $eventLoggingService->createEventRecord(
            $this->event->event_type_id ?? 'signed_quotes',
            'init',
            ['quotes' => $normalizedQuotes],
            'Validating signed quotes',
            $this->record->id,
            $this->event->id
        );

        ValidateEntitiesJob::dispatch(['quotes' => $normalizedQuotes], $this->event, $validationRecord)
            ->onQueue('validation');

        $this->record->update([
            'status' => 'success',
            'message' => sprintf('Signed quotes dispatched for validation (%d)', count($normalizedQuotes)),
            'details' => [
                'quotes_total' => count($normalizedQuotes),
            ],
        ]);
    }
}
