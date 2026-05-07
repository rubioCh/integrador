<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'treble_template_id',
        'name',
        'priority',
        'trigger_property',
        'trigger_value',
        'conditions',
        'active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function trebleTemplate(): BelongsTo
    {
        return $this->belongsTo(TrebleTemplate::class);
    }
}
