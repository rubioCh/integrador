<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'event_type_id',
        'platform_id',
        'to_event_id',
        'type',
        'schedule_expression',
        'last_executed_at',
        'command_sql',
        'enable_update_hubdb',
        'hubdb_table_id',
        'subscription_type',
        'method_name',
        'endpoint_api',
        'active',
        'payload_mapping',
        'meta',
    ];

    protected $casts = [
        'active' => 'boolean',
        'enable_update_hubdb' => 'boolean',
        'last_executed_at' => 'datetime',
        'payload_mapping' => 'array',
        'meta' => 'array',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function to_event(): BelongsTo
    {
        return $this->belongsTo(self::class, 'to_event_id');
    }

    public function from_events(): HasMany
    {
        return $this->hasMany(self::class, 'to_event_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    public function propertyRelationships(): HasMany
    {
        return $this->hasMany(PropertyRelationship::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_event')
            ->withTimestamps();
    }

    public function eventTriggers(): HasMany
    {
        return $this->hasMany(EventTrigger::class);
    }

    public function httpConfig(): HasOne
    {
        return $this->hasOne(EventHttpConfig::class);
    }

    public function idempotencyKeys(): HasMany
    {
        return $this->hasMany(EventIdempotencyKey::class);
    }

    public function getMethodName(): ?string
    {
        if (is_string($this->method_name) && trim($this->method_name) !== '') {
            return trim($this->method_name);
        }

        if (($this->platform?->type ?? null) === 'hubspot') {
            $subscriptionType = strtolower(trim((string) ($this->subscription_type ?: $this->event_type_id ?: '')));

            $mappedMethod = match ($subscriptionType) {
                'contact.propertychange' => 'contactPropertyChange',
                'company.propertychange' => 'companyPropertyChange',
                'deal.propertychange' => 'dealPropertyChange',
                'object.propertychange' => 'objectPropertyChange',
                'invoice.propertychange' => 'invoicePropertyChange',
                'hubspot.property.changed' => 'objectPropertyChange',
                'contact.creation' => 'contactCreatedWebhook',
                'company.creation' => 'companyCreatedWebhook',
                default => null,
            };

            if ($mappedMethod) {
                return $mappedMethod;
            }
        }

        return null;
    }

    public function getSubscriptionType(): ?string
    {
        return $this->subscription_type ?: $this->event_type_id;
    }

    public function getEventTypeEnum(): ?EventType
    {
        return EventType::tryFrom((string) $this->event_type_id);
    }

    public function getEventTypeLabel(): ?string
    {
        return $this->getEventTypeEnum()?->label();
    }

    public function getEventClass(): ?string
    {
        return $this->getEventTypeEnum()?->eventClass();
    }
}
