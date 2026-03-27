<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Record;
use App\Services\EventLoggingService;
use App\Services\Hubspot\HubspotApiServiceRefactored;
use App\Services\SignedQuotesPipelineService;
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

    public function handle(
        EventLoggingService $eventLoggingService,
        HubspotApiServiceRefactored $hubspotApi,
        SignedQuotesPipelineService $pipelineService
    ): void
    {
        $this->record->update([
            'status' => 'processing',
            'message' => 'Creating quote in target platform',
        ]);

        $targetPlatform = (string) Arr::get($this->payload, 'target_platform', 'odoo');
        $createdQuotes = [];
        $sourcePlatformUpdates = [];

        foreach (Arr::get($this->payload, 'quotes', []) as $quote) {
            $executionResponse = [
                'quote_id' => Arr::get($quote, 'quote_id'),
                'hubspot_quote_id' => Arr::get($quote, 'hubspot_quote_id'),
                'target_platform' => $targetPlatform,
                'entity_results' => Arr::get($quote, 'entity_results', []),
                'hubspot_sync_metadata' => Arr::get($quote, 'hubspot_sync_metadata', []),
                'status' => 'created',
                'external_id' => Arr::get($quote, 'target_quote_id')
                    ?? Arr::get($quote, 'quote_id'),
            ];

            $createdQuotes[] = $executionResponse;
            $sourcePlatformUpdates[] = $this->storeExecutionResponseInSourcePlatform(
                $quote,
                $targetPlatform,
                $executionResponse,
                $hubspotApi,
                $pipelineService
            );
        }

        $writeBackFailures = array_values(array_filter(
            $sourcePlatformUpdates,
            static fn (array $update): bool => ($update['success'] ?? false) === false
                && ($update['skipped'] ?? false) === false
        ));

        $details = [
            'target_platform' => $targetPlatform,
            'summary' => Arr::get($this->payload, 'summary', []),
            'quotes' => $createdQuotes,
            'source_platform_updates' => $sourcePlatformUpdates,
        ];

        if ($writeBackFailures !== []) {
            $eventLoggingService->logEventWarning(
                $this->record,
                'Quote creation completed with source platform write-back warnings.',
                $details
            );

            return;
        }

        $this->record->update([
            'status' => 'success',
            'message' => 'Quote creation completed and source platform updated',
            'details' => [
                ...$details,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, mixed>  $executionResponse
     * @return array<string, mixed>
     */
    private function storeExecutionResponseInSourcePlatform(
        array $quote,
        string $targetPlatform,
        array $executionResponse,
        HubspotApiServiceRefactored $hubspotApi,
        SignedQuotesPipelineService $pipelineService
    ): array {
        $sourcePlatformType = strtolower((string) ($this->event->platform->type ?? ''));
        if ($sourcePlatformType !== 'hubspot') {
            return [
                'success' => false,
                'skipped' => true,
                'reason' => 'source_platform_not_supported',
                'source_platform' => $sourcePlatformType !== '' ? $sourcePlatformType : null,
            ];
        }

        $hubspotQuoteId = trim((string) Arr::get($quote, 'hubspot_quote_id', ''));
        if ($hubspotQuoteId === '') {
            return [
                'success' => false,
                'skipped' => true,
                'reason' => 'missing_hubspot_quote_id',
                'source_platform' => 'hubspot',
            ];
        }

        $properties = $pipelineService->buildHubspotQuoteSyncProperties($quote, $targetPlatform, $executionResponse);
        $response = $hubspotApi->updateObject('quotes', $hubspotQuoteId, $properties);

        return [
            'success' => (bool) ($response['success'] ?? false),
            'skipped' => false,
            'source_platform' => 'hubspot',
            'hubspot_quote_id' => $hubspotQuoteId,
            'properties' => $properties,
            'response' => [
                'status_code' => $response['status_code'] ?? null,
                'data' => $response['data'] ?? null,
                'error' => $response['error'] ?? null,
                'message' => $response['message'] ?? null,
            ],
        ];
    }
}
