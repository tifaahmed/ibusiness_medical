<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One device or browser that opened the website or an app. Created on the first
 * hit and enriched whenever the visitor reveals more (GPS, phone, email, sign-in).
 */
class Visitor extends Model
{
    protected $fillable = [
        'visitor_key', 'source', 'ip_address', 'user_agent', 'device_type', 'device_model', 'os', 'browser',
        'app_version', 'locale', 'first_referrer', 'is_bot',
        'latitude', 'longitude', 'location_accuracy', 'location_at',
        'governorate_id', 'city_id', 'governorate_name', 'city_name',
        'user_id', 'membership_id', 'name', 'phone', 'email', 'identified_at',
        'visits_count', 'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_at' => 'datetime',
            'identified_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(PageVisit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function isIdentified(): bool
    {
        return $this->user_id !== null || $this->phone || $this->email;
    }

    /** @param  Builder<self>  $query */
    public function scopeFromPeople(Builder $query): void
    {
        $query->where('is_bot', false);
    }
}
