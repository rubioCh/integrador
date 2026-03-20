<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\SignedQuotesPipelineService;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class ValidateEntitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 2;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        public array $payload,
        public Event $event,
        public Record $record
    ) {
        $this->onQueue('validation');
    }

    public function handle(EventLoggingService $eventLoggingService, SignedQuotesPipelineService $pipelineService): void
    {
        $this->record->update([
            'status' => 'processing',
            'message' => 'Validating entities',
        ]);

        $targetPlatform = $this->resolveTargetPlatform();
        $quotes = Arr::get($this->payload, 'quotes', []);
        $evaluatedQuotes = $pipelineService->evaluateEntities($quotes, $targetPlatform);

        $summary = $this->buildSummary($evaluatedQuotes);

        $createRecord = $eventLoggingService->createEventRecord(
            $this->event->event_type_id ?? 'signed_quotes',
            'init',
            [
                'quotes' => $evaluatedQuotes,
                'summary' => $summary,
                'target_platform' => $targetPlatform,
            ],
            'Creating or updating entities',
            $this->record->id,
            $this->event->id
        );

        CreateOrUpdateEntityJob::dispatch([
            'quotes' => $evaluatedQuotes,
            'summary' => $summary,
            'target_platform' => $targetPlatform,
        ], $this->event, $createRecord)
            ->onQueue('processing');

        $this->record->update([
            'status' => 'success',
            'message' => 'Entity validation completed',
            'details' => [
                'target_platform' => $targetPlatform,
                'summary' => $summary,
            ],
        ]);
    }

    private function resolveTargetPlatform(): string
    {
        $target = $this->event->meta['target_platform']
            ?? $this->event->to_event?->platform?->type
            ?? 'odoo';

        return (string) $target;
    }

    /**
     * @param  array<int, array<string, mixed>>  $quotes
     * @return array<string, int>
     */
    private function buildSummary(array $quotes): array
    {
        $summary = [
            'create' => 0,
            'update' => 0,
            'no_change' => 0,
        ];

        foreach ($quotes as $quote) {
            $actions = [
                Arr::get($quote, 'entity_actions.company.action'),
                Arr::get($quote, 'entity_actions.contact.action'),
            ];

            foreach (Arr::get($quote, 'entity_actions.products', []) as $productAction) {
                $actions[] = Arr::get($productAction, 'action');
            }

            foreach ($actions as $action) {
                if (isset($summary[$action])) {
                    $summary[$action]++;
                }
            }
        }

        return $summary;
    }
}
