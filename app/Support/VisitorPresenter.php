<?php

namespace App\Support;

use App\Models\PageVisit;
use App\Models\Visitor;

/** Shapes visitors and visits for the admin pages (one place, so the tabs never drift). */
class VisitorPresenter
{
    /** @return array<string, mixed> */
    public static function visitor(Visitor $v): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $v->id,
            'source' => $v->source,
            'ip_address' => $v->ip_address,
            'device_type' => $v->device_type,
            'device_model' => $v->device_model,
            'os' => $v->os,
            'browser' => $v->browser,
            'app_version' => $v->app_version,
            'locale' => $v->locale,
            'is_bot' => $v->is_bot,
            'latitude' => $v->latitude,
            'longitude' => $v->longitude,
            'location_at' => $v->location_at?->toIso8601String(),
            'governorate' => $v->governorate?->getTranslation('name', $locale) ?: $v->governorate_name,
            'city' => $v->city?->getTranslation('name', $locale) ?: $v->city_name,
            'user_id' => $v->user_id,
            'membership_id' => $v->membership_id,
            'name' => $v->user?->name ?: $v->name,
            'phone' => $v->user?->phone ?: $v->phone,
            'email' => $v->user?->email ?: $v->email,
            'is_member' => $v->user_id !== null,
            'visits_count' => $v->visits_count,
            'first_seen_at' => $v->first_seen_at?->toIso8601String(),
            'last_seen_at' => $v->last_seen_at?->toIso8601String(),
            'first_referrer' => $v->first_referrer,
        ];
    }

    /** @return array<string, mixed> */
    public static function visit(PageVisit $p): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $p->id,
            'visited_at' => $p->visited_at?->toIso8601String(),
            'page' => $p->page,
            'url' => $p->url,
            'referrer' => $p->referrer,
            'source' => $p->source,
            'ip_address' => $p->ip_address,
            'governorate' => $p->governorate?->getTranslation('name', $locale),
            'city' => $p->city?->getTranslation('name', $locale),
            'product' => $p->product ? ['id' => $p->product->id, 'slug' => $p->product->slug, 'name' => $p->product->getTranslation('name', $locale)] : null,
            'facility' => $p->facility ? ['id' => $p->facility->id, 'slug' => $p->facility->slug, 'name' => $p->facility->getTranslation('name', $locale)] : null,
            'visitor' => $p->relationLoaded('visitor') && $p->visitor ? self::visitor($p->visitor) : null,
        ];
    }
}
