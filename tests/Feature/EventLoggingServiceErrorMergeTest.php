<?php

namespace Tests\Feature;

use App\Models\Record;
use App\Services\EventLoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventLoggingServiceErrorMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_preserves_existing_record_details_when_logging_an_error(): void
    {
        $record = Record::query()->create([
            'event_type' => 'object.updated',
            'status' => 'processing',
            'payload' => [],
            'message' => 'Processing',
            'details' => [
                'service_output' => [
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'HubSpot rejected the update.',
                    ],
                ],
                'service_message' => 'Failed to update HubSpot contact from destination response.',
                'hubspot_note' => [
                    'attempted' => true,
                    'success' => true,
                    'note_id' => '1001',
                ],
            ],
        ]);

        app(EventLoggingService::class)->logEventError(
            $record,
            new \RuntimeException('Failed to update HubSpot contact from destination response.')
        );

        $record->refresh();

        $this->assertSame('error', $record->status);
        $this->assertSame('Failed to update HubSpot contact from destination response.', $record->message);
        $this->assertSame('VALIDATION_ERROR', data_get($record->details, 'service_output.error.code'));
        $this->assertSame('Failed to update HubSpot contact from destination response.', data_get($record->details, 'service_message'));
        $this->assertSame('1001', data_get($record->details, 'hubspot_note.note_id'));
        $this->assertSame(\RuntimeException::class, data_get($record->details, 'exception'));
        $this->assertSame(0, data_get($record->details, 'code'));
    }
}
