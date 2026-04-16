<?php

namespace App\Services;

use App\Models\Record;
use Exception;

class EventLoggingService
{
    /**
     * @var list<string>
     */
    private const ALLOWED_STATUSES = [
        'init',
        'processing',
        'success',
        'error',
        'warning',
    ];

    public function createEventRecord(
        string $eventType,
        string $status,
        array $payload,
        string $message,
        ?int $parentRecordId = null,
        ?int $eventId = null
    ): Record {
        $this->assertStatusIsCanonical($status);

        return Record::query()->create([
            'event_id' => $eventId,
            'record_id' => $parentRecordId,
            'event_type' => $eventType,
            'status' => $status,
            'payload' => $payload,
            'message' => $message,
        ]);
    }

    public function logEventSuccess(Record $record, string $message): void
    {
        $record->update([
            'status' => 'success',
            'message' => $message,
        ]);
    }

    public function logEventError(Record $record, Exception $exception): void
    {
        $existingDetails = is_array($record->details) ? $record->details : [];

        $record->update([
            'status' => 'error',
            'message' => $exception->getMessage(),
            'details' => array_merge($existingDetails, [
                'exception' => get_class($exception),
                'code' => $exception->getCode(),
            ]),
        ]);
    }

    public function logEventWarning(Record $record, string $message, array $details = []): void
    {
        $record->update([
            'status' => 'warning',
            'message' => $message,
            'details' => empty($details) ? null : $details,
        ]);
    }

    private function assertStatusIsCanonical(string $status): void
    {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid record status [%s]. Use canonical statuses only.', $status)
            );
        }
    }
}
