<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'credentials',
        'settings',
        'active',
    ];

    protected $casts = [
        'credentials' => 'array',
        'settings' => 'array',
        'active' => 'boolean',
    ];

    protected $hidden = [
        'credentials',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
