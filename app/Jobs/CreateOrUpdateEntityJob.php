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

class CreateOrUpdateEntityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 300;

    public function __construct(
        public array $payload,
        public Event $event,
        public Record $record
    ) {
        $this->onQueue('processing');
    }

    public function handle(EventLoggingService $eventLoggingService, SignedQuotesPipelineService $pipelineService): void
    {
        $this->record->update([
            'status' => 'processing',
            'message' => 'Creating or updating entities',
        ]);

        $targetPlatform = (string) Arr::get($this->payload, 'target_platform', 'odoo');
        $processedQuotes = [];

        foreach (Arr::get($this->payload, 'quotes', []) as $quote) {
            $processedQuotes[] = $this->processQuoteEntities($quote, $targetPlatform, $pipelineService);
        }

        $updateRecord = $eventLoggingService->createEventRecord(
            $this->event->event_type_id ?? 'signed_quotes',
            'init',
            [
                'quotes' => $processedQuotes,
                'summary' => Arr::get($this->payload, 'summary', []),
                'target_platform' => $targetPlatform,
            ],
            'Updating HubSpot with entity sync metadata',
            $this->record->id,
            $this->event->id
        );

        UpdateHubSpotJob::dispatch([
            'quotes' => $processedQuotes,
            'summary' => Arr::get($this->payload, 'summary', []),
            'target_platform' => $targetPlatform,
        ], $this->event, $updateRecord)
            ->onQueue('update');

        $this->record->update([
            'status' => 'success',
            'message' => 'Entity create/update completed',
            'details' => [
                'target_platform' => $targetPlatform,
                'quotes_total' => count($processedQuotes),
            ],
        ]);
    }

    private function processQuoteEntities(array $quote, string $targetPlatform, SignedQuotesPipelineService $pipelineService): array
    {
        $company = $this->processEntityAction(
            Arr::get($quote, 'entity_actions.company', []),
            $quote['quote_id'] ?? 'quote',
            $targetPlatform,
            $pipelineService
        );

        $contact = $this->processEntityAction(
            Arr::get($quote, 'entity_actions.contact', []),
            $quote['quote_id'] ?? 'quote',
            $targetPlatform,
            $pipelineService
        );

        $products = [];
        foreach (Arr::get($quote, 'entity_actions.products', []) as $index => $productAction) {
            $products[] = $this->processEntityAction(
                $productAction,
                ($quote['quote_id'] ?? 'quote') . '_product_' . ($index + 1),
                $targetPlatform,
                $pipelineService
            );
        }

        return [
            'quote_id' => $quote['quote_id'] ?? null,
            'hubspot_quote_id' => $quote['hubspot_quote_id'] ?? null,
            'entity_results' => [
                'company' => $company,
                'contact' => $contact,
                'products' => $products,
            ],
            'raw' => $quote['raw'] ?? [],
        ];
    }

    private function processEntityAction(array $action, string $reference, string $targetPlatform, SignedQuotesPipelineService $pipelineService): array
    {
        $operation = $action['action'] ?? 'no_change';
        $entityType = $action['entity_type'] ?? 'entity';
        $targetId = $action['target_id'] ?? null;

        if ($operation === 'create') {
            $targetId = $pipelineService->createExternalId($targetPlatform, $entityType, $reference);
        }

        return [
            'entity_type' => $entityType,
            'operation' => $operation,
            'target_id' => $targetId,
            'changed_fields' => $action['changed_fields'] ?? [],
            'fields' => $action['fields'] ?? [],
        ];
    }
}
