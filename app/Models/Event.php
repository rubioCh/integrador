<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'event_type_id',
        'platform_id',
        'to_event_id',
        'type',
        'subscription_type',
        'method_name',
        'endpoint_api',
        'active',
        'payload_mapping',
        'meta',
    ];

    protected $casts = [
        'active' => 'boolean',
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

    public function getMethodName(): ?string
    {
        return $this->method_name;
    }

    public function getEventTypeEnum(): ?EventType
    {
        return EventType::tryFrom((string) $this->event_type_id);
    }
}
