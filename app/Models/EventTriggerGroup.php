<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventTriggerGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'operator',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(EventTrigger::class, 'event_trigger_group_id');
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(EventTriggerGroupCondition::class, 'event_trigger_group_id');
    }
}
