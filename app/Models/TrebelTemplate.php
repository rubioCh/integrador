<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrebelTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'external_template_id',
        'payload_mapping',
        'active',
    ];

    protected $casts = [
        'payload_mapping' => 'array',
        'active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function messageRules(): HasMany
    {
        return $this->hasMany(MessageRule::class);
    }
}
