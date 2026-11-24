<?php

namespace App\Support;

use App\Models\DevicePushToken;
use Illuminate\Database\Eloquent\Builder;

/**
 * The filters of a "group" push. One place builds the device query, so the count the admin sees before sending and
 * what the job really sends can never disagree. Empty / 'any' filters narrow nothing.
 */
class PushAudience
{
    public const PLATFORMS = ['any', 'android', 'ios'];

    public const WHO = ['any', 'members', 'guests'];

    public const CARDS = ['any', 'trial', 'paid', 'expired', 'no_card'];

    public const KINDS = ['any', 'individual', 'company'];

    public const GENDERS = ['any', 'male', 'female'];

    public const LOCALES = ['any', 'ar', 'en'];

    /** Keeps only known keys with known values. @return array<string, string|int> */
    public static function clean(array $raw): array
    {
        $out = [];
        foreach (['platform' => self::PLATFORMS, 'who' => self::WHO, 'card' => self::CARDS, 'kind' => self::KINDS, 'gender' => self::GENDERS, 'locale' => self::LOCALES] as $key => $allowed) {
            $v = $raw[$key] ?? 'any';
            if (in_array($v, $allowed, true) && $v !== 'any') {
                $out[$key] = $v;
            }
        }
        if (is_numeric($raw['governorate_id'] ?? null) && (int) $raw['governorate_id'] > 0) {
            $out['governorate_id'] = (int) $raw['governorate_id'];
        }

        return $out;
    }

    public static function query(array $filters): Builder
    {
        $q = DevicePushToken::query();
        $f = self::clean($filters);

        if (isset($f['platform'])) {
            $q->where('platform', $f['platform']);
        }
        if (isset($f['locale'])) {
            $q->where('locale', 'like', $f['locale'].'%');
        }
        if (($f['who'] ?? null) === 'guests' || ($f['card'] ?? null) === 'no_card') {
            // A phone nobody is signed in on, or a member with no card.
            if (($f['who'] ?? null) === 'guests') {
                $q->whereNull('user_id');
            } else {
                $q->whereNotNull('user_id')->whereDoesntHave('user.memberships', fn ($m) => $m->primary());
            }
        }
        if (($f['who'] ?? null) === 'members') {
            $q->whereNotNull('user_id');
        }
        if (isset($f['gender'])) {
            $q->whereHas('user', fn ($u) => $u->where('gender', $f['gender']));
        }

        $today = now()->toDateString();
        if ((isset($f['card']) && $f['card'] !== 'no_card') || isset($f['kind']) || isset($f['governorate_id'])) {
            $q->whereHas('user.memberships', function ($m) use ($f, $today) {
                $m->primary();
                match ($f['card'] ?? 'any') {
                    'trial' => $m->onTrial()->where('is_active', true)->where(fn ($e) => $e->whereNull('expiration_date')->orWhere('expiration_date', '>=', $today)),
                    'paid' => $m->notOnTrial()->where('is_active', true)->where(fn ($e) => $e->whereNull('expiration_date')->orWhere('expiration_date', '>=', $today)),
                    'expired' => $m->whereNotNull('expiration_date')->where('expiration_date', '<', $today),
                    default => null,
                };
                if (($f['kind'] ?? null) === 'company') {
                    $m->whereNotNull('company_id');
                } elseif (($f['kind'] ?? null) === 'individual') {
                    $m->whereNull('company_id');
                }
                if (isset($f['governorate_id'])) {
                    $m->where('governorate_id', $f['governorate_id']);
                }
            });
        }

        return $q;
    }
}
