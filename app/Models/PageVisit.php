<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One page or screen a visitor opened. Append-only.
 */
class PageVisit extends Model
{
    protected $fillable = [
        'visitor_id', 'source', 'page', 'url', 'locale', 'referrer', 'product_id', 'facility_id', 'user_id',
        'ip_address', 'user_agent', 'latitude', 'longitude', 'governorate_id', 'city_id',
        'is_bot', 'visited_at', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'visited_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeFromPeople(Builder $query): void
    {
        $query->where('is_bot', false);
    }
}
