<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PlatformConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'platform_type',
        'name',
        'slug',
        'base_url',
        'signature_header',
        'webhook_secret',
        'credentials',
        'settings',
        'active',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'webhook_secret' => 'encrypted',
        'active' => 'boolean',
    ];

    protected $hidden = [
        'credentials',
        'webhook_secret',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public static function generateWebhookSecret(): string
    {
        return Str::random(48);
    }
}
