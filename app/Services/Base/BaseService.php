<?php

namespace App\Services\Base;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class BaseService
{
    /**
     * @var list<string>
     */
    protected const CANONICAL_RECORD_STATUSES = [
        'init',
        'processing',
        'success',
        'error',
        'warning',
    ];

    public function __construct(
        protected Platform $platform,
        protected ?Event $event = null,
        protected ?Record $record = null
    ) {
    }

    /**
     * @throws ModelNotFoundException
     */
    public function loadEvent(int $event_id): Event
    {
        return Event::query()
            ->with(['platform', 'to_event', 'properties'])
            ->findOrFail($event_id);
    }

    public function execute(
        string $subscriptionType,
        $class = null,
        $payload = null,
        bool $sendData = true
    ) {
        if ($class !== null && method_exists($this, (string) $class)) {
            return $this->{$class}($payload, $sendData, $subscriptionType);
        }

        if (method_exists($this, $subscriptionType)) {
            return $this->{$subscriptionType}($payload, $sendData);
        }

        throw new \BadMethodCallException(
            sprintf('Method [%s] not found in [%s].', $subscriptionType, static::class)
        );
    }

    public function createRecord(
        string $type,
        string $status,
        $data,
        ?int $parent_record_id = null,
        string $message = 'Processing data',
        $created_at = null,
        $updated_at = null
    ): Record {
        if (! in_array($status, self::CANONICAL_RECORD_STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid record status [%s]. Use canonical statuses only.', $status)
            );
        }

        $payload = is_array($data) ? $data : ['value' => $data];

        return Record::query()->create([
            'event_id' => $this->event?->id,
            'record_id' => $parent_record_id,
            'event_type' => $type,
            'status' => $status,
            'payload' => $payload,
            'message' => $message,
            'created_at' => $created_at,
            'updated_at' => $updated_at,
        ]);
    }
}
