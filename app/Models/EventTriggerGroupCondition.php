<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTriggerGroupCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_trigger_group_id',
        'field',
        'operator',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(EventTriggerGroup::class, 'event_trigger_group_id');
    }
}
