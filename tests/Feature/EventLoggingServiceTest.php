<?php

namespace Tests\Feature;

use App\Services\EventLoggingService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventLoggingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_init_record(): void
    {
        $service = app(EventLoggingService::class);

        $record = $service->createEventRecord(
            eventType: 'company.created',
            status: 'init',
            payload: ['id' => 1],
            message: 'Record initialized',
        );

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'event_type' => 'company.created',
            'status' => 'init',
        ]);
    }

    public function test_it_logs_success_status(): void
    {
        $service = app(EventLoggingService::class);

        $record = $service->createEventRecord(
            eventType: 'company.created',
            status: 'init',
            payload: [],
            message: 'Pending',
        );

        $service->logEventSuccess($record, 'Done');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'status' => 'success',
            'message' => 'Done',
        ]);
    }

    public function test_it_logs_error_status(): void
    {
        $service = app(EventLoggingService::class);

        $record = $service->createEventRecord(
            eventType: 'company.created',
            status: 'processing',
            payload: [],
            message: 'Processing',
        );

        $service->logEventError($record, new Exception('Boom'));

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'status' => 'error',
            'message' => 'Boom',
        ]);
    }
}
