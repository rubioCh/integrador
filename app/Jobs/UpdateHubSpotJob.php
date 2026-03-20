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

class UpdateHubSpotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 180;

    public function __construct(
        public array $payload,
        public Event $event,
        public Record $record
    ) {
        $this->onQueue('update');
    }

    public function handle(EventLoggingService $eventLoggingService, SignedQuotesPipelineService $pipelineService): void
    {
        $this->record->update([
            'status' => 'processing',
            'message' => 'Updating HubSpot',
        ]);

        $targetPlatform = (string) Arr::get($this->payload, 'target_platform', 'odoo');
        $quotesWithMetadata = [];

        foreach (Arr::get($this->payload, 'quotes', []) as $quote) {
            $entityResults = Arr::get($quote, 'entity_results', []);

            $hubspotMetadata = [
                'company' => $pipelineService->buildHubspotSyncMetadata(
                    [
                        'action' => Arr::get($entityResults, 'company.operation', 'no_change'),
                        'changed_fields' => Arr::get($entityResults, 'company.changed_fields', []),
                    ],
                    $targetPlatform
                ),
                'contact' => $pipelineService->buildHubspotSyncMetadata(
                    [
                        'action' => Arr::get($entityResults, 'contact.operation', 'no_change'),
                        'changed_fields' => Arr::get($entityResults, 'contact.changed_fields', []),
                    ],
                    $targetPlatform
                ),
                'products' => array_map(
                    fn (array $productResult): array => $pipelineService->buildHubspotSyncMetadata(
                        [
                            'action' => Arr::get($productResult, 'operation', 'no_change'),
                            'changed_fields' => Arr::get($productResult, 'changed_fields', []),
                        ],
                        $targetPlatform
                    ),
                    Arr::get($entityResults, 'products', [])
                ),
            ];

            $quotesWithMetadata[] = $quote + [
                'hubspot_sync_metadata' => $hubspotMetadata,
            ];
        }

        $quoteRecord = $eventLoggingService->createEventRecord(
            $this->event->event_type_id ?? 'signed_quotes',
            'init',
            [
                'quotes' => $quotesWithMetadata,
                'summary' => Arr::get($this->payload, 'summary', []),
                'target_platform' => $targetPlatform,
            ],
            'Creating quote in target platform',
            $this->record->id,
            $this->event->id
        );

        CreateQuoteJob::dispatch([
            'quotes' => $quotesWithMetadata,
            'summary' => Arr::get($this->payload, 'summary', []),
            'target_platform' => $targetPlatform,
        ], $this->event, $quoteRecord)
            ->onQueue('signed-quotes');

        $this->record->update([
            'status' => 'success',
            'message' => 'HubSpot update completed',
            'details' => [
                'target_platform' => $targetPlatform,
                'quotes_total' => count($quotesWithMetadata),
            ],
        ]);
    }
}
