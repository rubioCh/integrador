<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'property_id',
        'related_property_id',
        'mapping_key',
        'active',
        'meta',
    ];

    protected $casts = [
        'active' => 'boolean',
        'meta' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function relatedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'related_property_id');
    }
}
