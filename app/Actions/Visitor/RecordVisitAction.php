<?php

namespace App\Actions\Visitor;

use App\Models\Facility;
use App\Models\PageVisit;
use App\Models\Product;
use App\Models\User;
use App\Models\Visitor;
use App\Support\UserAgentInfo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records one page/screen view and folds everything the request revealed into
 * the visitor row. A value already known is only replaced by a newer, non-empty
 * one — a hit that carries no GPS never wipes the GPS an earlier hit gave.
 *
 * `$verifiedUser` is set ONLY when the caller proved who it is (a Sanctum token);
 * phone/email typed into a form are stored as claims and never link a member.
 */
class RecordVisitAction
{
    public function __construct(private readonly ResolveVisitorPlace $places) {}

    /**
     * @param  array<string, mixed>  $d  validated visit payload plus `ip_address` / `source`
     */
    public function handle(array $d, ?User $verifiedUser = null): PageVisit
    {
        // Into OUR timezone: the website sends UTC (`…+00:00`), and a Carbon is saved as its own
        // wall time, so an unconverted one lands three hours off from everything else here.
        $visitedAt = ! empty($d['visited_at']) ? Carbon::parse($d['visited_at'])->setTimezone(config('app.timezone')) : now();
        if ($visitedAt->isFuture()) {
            $visitedAt = now();
        }

        return DB::transaction(function () use ($d, $verifiedUser, $visitedAt) {
            $visitor = $this->visitor($d['visitor_key'], $d['source']);
            $ua = UserAgentInfo::parse($d['user_agent'] ?? null);

            $this->fill($visitor, [
                'ip_address' => $d['ip_address'] ?? null,
                'user_agent' => $d['user_agent'] ?? null,
                'device_type' => $ua['device_type'],
                'os' => $d['os'] ?? $ua['os'],
                'browser' => $ua['browser'],
                'device_model' => $d['device_model'] ?? null,
                'app_version' => $d['app_version'] ?? null,
                'locale' => $d['locale'] ?? null,
                'name' => $d['name'] ?? ($verifiedUser?->name),
                'phone' => $d['phone'] ?? ($verifiedUser?->phone),
                'email' => $d['email'] ?? ($verifiedUser?->email),
            ]);
            if ($ua['is_bot']) {
                $visitor->is_bot = true;
            }
            if ($visitor->first_referrer === null && ! empty($d['referrer'])) {
                $visitor->first_referrer = $d['referrer'];
            }

            $wasIdentified = $visitor->isIdentified();
            if ($verifiedUser) {
                $visitor->user_id = $verifiedUser->id;
                $visitor->membership_id = $verifiedUser->memberships()->latest('id')->value('id') ?? $visitor->membership_id;
            }
            if (! $wasIdentified && $visitor->isIdentified()) {
                $visitor->identified_at = now();
            }

            [$govId, $cityId] = $this->place($visitor, $d);

            $visitor->visits_count = ($visitor->visits_count ?? 0) + 1;
            $visitor->first_seen_at = $visitor->first_seen_at ? min($visitor->first_seen_at, $visitedAt) : $visitedAt;
            $visitor->last_seen_at = $visitor->last_seen_at ? max($visitor->last_seen_at, $visitedAt) : $visitedAt;
            $visitor->save();

            return PageVisit::create([
                'visitor_id' => $visitor->id,
                'source' => $d['source'],
                'page' => $d['page'],
                'url' => $d['url'] ?? null,
                'locale' => $d['locale'] ?? null,
                'referrer' => $d['referrer'] ?? null,
                'product_id' => $this->subject($d, 'product', Product::class),
                'facility_id' => $this->subject($d, 'facility', Facility::class),
                'user_id' => $verifiedUser?->id,
                'ip_address' => $d['ip_address'] ?? null,
                'user_agent' => $d['user_agent'] ?? null,
                'latitude' => $d['latitude'] ?? null,
                'longitude' => $d['longitude'] ?? null,
                'governorate_id' => $govId,
                'city_id' => $cityId,
                'is_bot' => (bool) $visitor->is_bot,
                'visited_at' => $visitedAt,
            ]);
        });
    }

    private function visitor(string $key, string $source): Visitor
    {
        $visitor = Visitor::where('visitor_key', $key)->first();
        if ($visitor) {
            return $visitor;
        }

        try {
            return Visitor::create(['visitor_key' => $key, 'source' => $source, 'visits_count' => 0, 'is_bot' => false]);
        } catch (QueryException $e) {
            // Two first hits raced; the other one won.
            return Visitor::where('visitor_key', $key)->firstOrFail();
        }
    }

    /** @param  array<string, mixed>  $values */
    private function fill(Visitor $visitor, array $values): void
    {
        foreach ($values as $column => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }
            if ($value !== null && $value !== '') {
                $visitor->{$column} = $value;
            }
        }
    }

    /** @return array{0: ?int, 1: ?int} */
    private function place(Visitor $visitor, array $d): array
    {
        $lat = isset($d['latitude']) ? (float) $d['latitude'] : null;
        $lng = isset($d['longitude']) ? (float) $d['longitude'] : null;

        if ($lat === null || $lng === null) {
            $this->fill($visitor, [
                'governorate_name' => $d['governorate_name'] ?? null,
                'city_name' => $d['city_name'] ?? null,
            ]);

            return [$visitor->governorate_id, $visitor->city_id];
        }

        $place = $this->places->handle($lat, $lng);
        $visitor->latitude = $lat;
        $visitor->longitude = $lng;
        $visitor->location_accuracy = $d['accuracy'] ?? $visitor->location_accuracy;
        $visitor->location_at = now();
        $visitor->governorate_id = $place['governorate_id'] ?? $visitor->governorate_id;
        $visitor->city_id = $place['city_id'] ?? $visitor->city_id;
        $this->fill($visitor, [
            'governorate_name' => $d['governorate_name'] ?? null,
            'city_name' => $d['city_name'] ?? null,
        ]);

        return [$place['governorate_id'], $place['city_id']];
    }

    /** @param  class-string  $model */
    private function subject(array $d, string $type, string $model): ?int
    {
        if (($d['subject_type'] ?? null) !== $type || empty($d['subject_slug'])) {
            return null;
        }

        return $model::where('slug', $d['subject_slug'])->value('id');
    }
}
