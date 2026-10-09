<?php

namespace App\Http\Controllers\Api\V1\Mobile\Concerns;

use App\Models\City;
use App\Models\FacilityType;
use App\Models\Governorate;
use Illuminate\Support\Facades\Cache;

/**
 * Tiny id => name maps (27 governorates, ~230 cities, ~20 types) kept in cache
 * per locale. Cards carry a place NAME, and resolving it from a map costs
 * nothing, where eager-loading `governorate` + `city` on every list is two
 * more queries per request.
 */
trait Lookups
{
    private const LOOKUP_TTL = 3600;

    /** @return array<int, string> */
    protected function governorateNames(): array
    {
        return Cache::remember('mobile:governorates:'.app()->getLocale(), self::LOOKUP_TTL, fn () => Governorate::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($g) => [$g->id => (string) $g->name])
            ->all());
    }

    /** @return array<int, string> */
    protected function cityNames(): array
    {
        return Cache::remember('mobile:cities:'.app()->getLocale(), self::LOOKUP_TTL, fn () => City::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($c) => [$c->id => (string) $c->name])
            ->all());
    }

    /** @return array<int, array{id: int, name: string, slug: string}> */
    protected function facilityTypeMap(): array
    {
        return Cache::remember('mobile:facility-types:'.app()->getLocale(), self::LOOKUP_TTL, fn () => FacilityType::query()
            ->get(['id', 'name', 'slug'])
            ->mapWithKeys(fn ($t) => [$t->id => ['id' => $t->id, 'name' => (string) $t->name, 'slug' => $t->slug]])
            ->all());
    }

    /**
     * Phone entries trimmed to what a button needs: the first dialable number
     * and the first number that takes WhatsApp.
     *
     * @param  array<int, array{number: string, type: string}>  $entries
     * @return array{phone: ?string, whatsapp: ?string}
     */
    protected function primaryPhones(array $entries): array
    {
        $entries = collect($entries);

        /* A line can be called unless it is WhatsApp-only; a line takes WhatsApp when its type says so.
           An entry with no type predates the types and is treated as a plain phone. */
        $call = $entries->first(fn ($e) => ($e['type'] ?? 'phone') !== 'whatsapp' && ! empty($e['number']));
        $whatsapp = $entries->first(fn ($e) => in_array($e['type'] ?? '', ['whatsapp', 'phone_whatsapp'], true) && ! empty($e['number']));

        return [
            'phone' => $call['number'] ?? null,
            'whatsapp' => $whatsapp['number'] ?? null,
        ];
    }

    /** Editor HTML down to plain text — a phone screen shows words, not markup. */
    protected function plainText(?string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], "\n", (string) $html)), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/[ \t]*\n[ \t\n]*/u", "\n", $text) ?? $text);
    }
}
