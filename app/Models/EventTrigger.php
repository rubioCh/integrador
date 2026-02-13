<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTrigger extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_trigger_group_id',
        'field',
        'operator',
        'value',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'value' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(EventTriggerGroup::class, 'event_trigger_group_id');
    }
}
