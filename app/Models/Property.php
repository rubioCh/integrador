<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform_id',
        'name',
        'key',
        'type',
        'required',
        'active',
        'meta',
    ];

    protected $casts = [
        'required' => 'boolean',
        'active' => 'boolean',
        'meta' => 'array',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'property_event')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_property')
            ->withTimestamps();
    }

    public function sourceRelationships(): HasMany
    {
        return $this->hasMany(PropertyRelationship::class, 'property_id');
    }

    public function targetRelationships(): HasMany
    {
        return $this->hasMany(PropertyRelationship::class, 'related_property_id');
    }
}
