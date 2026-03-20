<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventHttpConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'method',
        'base_url',
        'path',
        'headers_json',
        'query_json',
        'auth_mode',
        'auth_config_json',
        'timeout_seconds',
        'retry_policy_json',
        'idempotency_config_json',
        'allowlist_domains_json',
        'active',
    ];

    protected $casts = [
        'headers_json' => 'array',
        'query_json' => 'array',
        'auth_config_json' => 'array',
        'retry_policy_json' => 'array',
        'idempotency_config_json' => 'array',
        'allowlist_domains_json' => 'array',
        'active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
