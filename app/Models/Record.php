<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Record extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'record_id',
        'event_type',
        'status',
        'payload',
        'message',
        'details',
    ];

    protected $casts = [
        'payload' => 'array',
        'details' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'record_id');
    }

    public function childrens(): HasMany
    {
        return $this->hasMany(self::class, 'record_id');
    }
}
