<?php

namespace App\Actions\Facilities;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Support\DirectorySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * One typed phrase, answered across everything the directory can be found by.
 *
 * The directory's own listing endpoint searches facility names and nothing
 * else, which is right for a grid of results and wrong for a suggestion box: a
 * visitor holding a card in front of a clinic types the phone number off the
 * door, or the street, or just "الزقازيق". Those are five different kinds of
 * answer — a place to open, a branch to open, or a filter to apply — so they
 * come back grouped by kind rather than flattened into one ranked list the
 * consumer would have to take apart again.
 *
 * Every group is capped and reports its own `total`, so a consumer can offer
 * the first few and say honestly how many more there are.
 *
 * Both languages are matched whatever `X-Locale` asked for: somebody reading
 * the Arabic site still types "clinic" sometimes, and a facility named in only
 * one language would otherwise be unfindable from the other.
 */
class SearchDirectoryAction
{
    /** Rows per group unless a consumer asks for fewer. */
    public const PER_GROUP = 20;

    /** The most any consumer may ask for. */
    public const MAX_PER_GROUP = 50;

    /**
     * Below this the term matches most of the directory, so it is not a search.
     * Two characters is deliberate: Arabic place names are short.
     */
    public const MIN_TERM = 2;

    /** Short enough to be a fragment of a number rather than a whole one. */
    private const MIN_PHONE_DIGITS = 3;

    /** Names are matched in both, whichever language is being read. */
    private const LOCALES = ['ar', 'en'];

    /**
     * Everything matching the term, grouped by what kind of thing it is.
     *
     * @return array{query: string, groups: list<array{type: string, total: int, items: list<array<string, mixed>>}>}
     */
    public function handle(string $term, int $perGroup = self::PER_GROUP): array
    {
        $words = DirectorySearch::words($term);
        $perGroup = max(1, min($perGroup, self::MAX_PER_GROUP));

        if ($words === [] || mb_strlen(DirectorySearch::normalise($term)) < self::MIN_TERM) {
            return ['query' => $term, 'groups' => []];
        }

        $groups = [
            $this->facilities($words, $perGroup),
            $this->branches($words, DirectorySearch::digits($term), $perGroup),
            $this->cities($words, $perGroup),
            $this->governorates($words, $perGroup),
            $this->facilityTypes($words, $perGroup),
        ];

        return [
            'query' => $term,
            /* An empty group is not an answer; a consumer rendering a heading
               over nothing has to filter it out anyway. */
            'groups' => array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== [])),
        ];
    }

    /**
     * Facilities matched on their name or slug.
     *
     * @param  list<string>  $words
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function facilities(array $words, int $perGroup): array
    {
        $query = Facility::query()
            ->with(['facilityType', 'media'])
            ->where(function (Builder $builder) use ($words): void {
                $this->everyWord($builder, $words, [
                    ...$this->translatedExpressions('name'),
                    DirectorySearch::plain('slug'),
                ]);
            });

        return $this->group('facility', $query, $perGroup, $words[0], DirectorySearch::translated('name', App::getLocale()), fn (Facility $facility): array => [
            'id' => $facility->id,
            'slug' => $facility->slug,
            'name' => $facility->name,
            'logo' => $facility->logo ?: null,
            'discount_percent' => $facility->discount_percent,
            'type_name' => $facility->facilityType?->name,
        ]);
    }

    /**
     * Branches matched on their name, their address, or a phone number.
     *
     * The phone half is an OR rather than another word to match, because a
     * number is one token and never reads as words: "0100 000" is one search,
     * not two that both have to land.
     *
     * @param  list<string>  $words
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function branches(array $words, string $digits, int $perGroup): array
    {
        $query = FacilityBranch::query()
            ->with(['facility.media', 'city', 'governorate'])
            /* A branch whose facility has been deleted is a row nothing can
               open — it would render as a suggestion leading to a 404. */
            ->whereHas('facility')
            ->where(function (Builder $builder) use ($words, $digits): void {
                $builder->where(function (Builder $text) use ($words): void {
                    $this->everyWord($text, $words, [
                        ...$this->translatedExpressions('name'),
                        ...$this->translatedExpressions('address'),
                    ]);
                });

                if (strlen($digits) >= self::MIN_PHONE_DIGITS) {
                    $builder->orWhereRaw(
                        DirectorySearch::digitsOf('phone').' like ?',
                        ['%'.$digits.'%'],
                    );
                }
            });

        return $this->group('branch', $query, $perGroup, $words[0], DirectorySearch::translated('name', App::getLocale()), fn (FacilityBranch $branch): array => [
            'id' => $branch->id,
            'name' => $branch->name,
            'address' => $branch->address,
            'phone' => array_values($branch->phone ?? []),
            'city' => $branch->city?->name,
            'governorate' => $branch->governorate?->name,
            'facility_id' => $branch->facility_id,
            'facility_name' => $branch->facility?->name,
            'facility_slug' => $branch->facility?->slug,
            'logo' => $branch->facility?->logo ?: null,
        ]);
    }

    /**
     * Cities, each with how many facilities a visitor would find in it.
     *
     * @param  list<string>  $words
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function cities(array $words, int $perGroup): array
    {
        $query = City::query()
            ->with('governorate')
            ->where(fn (Builder $builder) => $this->everyWord($builder, $words, $this->translatedExpressions('name')))
            ->where(fn (Builder $hosting) => $this->hostsSomething($hosting));

        $group = $this->group('city', $query, $perGroup, $words[0], DirectorySearch::translated('name', App::getLocale()), fn (City $city): array => [
            'id' => $city->id,
            'name' => $city->name,
            'governorate' => $city->governorate?->name,
        ]);

        return $this->withCounts($group, 'city_id');
    }

    /**
     * @param  list<string>  $words
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function governorates(array $words, int $perGroup): array
    {
        $query = Governorate::query()
            ->where(fn (Builder $builder) => $this->everyWord($builder, $words, $this->translatedExpressions('name')))
            ->where(fn (Builder $hosting) => $this->hostsSomething($hosting));

        $group = $this->group('governorate', $query, $perGroup, $words[0], DirectorySearch::translated('name', App::getLocale()), fn (Governorate $governorate): array => [
            'id' => $governorate->id,
            'name' => $governorate->name,
        ]);

        return $this->withCounts($group, 'governorate_id');
    }

    /**
     * @param  list<string>  $words
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function facilityTypes(array $words, int $perGroup): array
    {
        $query = FacilityType::query()
            ->where(fn (Builder $builder) => $this->everyWord($builder, $words, $this->translatedExpressions('name')))
            /* A type nothing is filed under narrows the grid to nothing. */
            ->whereHas('facilities');

        $group = $this->group('facility_type', $query, $perGroup, $words[0], DirectorySearch::translated('name', App::getLocale()), fn (FacilityType $type): array => [
            'id' => $type->id,
            'name' => $type->name,
        ]);

        $ids = array_column($group['items'], 'id');

        if ($ids === []) {
            return $group;
        }

        $counts = Facility::query()
            ->whereIn('facility_type_id', $ids)
            ->groupBy('facility_type_id')
            ->pluck(DB::raw('count(*)'), 'facility_type_id');

        foreach ($group['items'] as $index => $item) {
            $group['items'][$index]['facility_count'] = (int) ($counts[$item['id']] ?? 0);
        }

        return $group;
    }

    /**
     * Count, order and shape one group.
     *
     * Counted from a clone before the limit, so a group can say how many rows
     * it is standing in front of rather than only how many it is showing.
     *
     * Ordering puts the rows that START with what was typed above the ones that
     * merely contain it — "نيل" should offer "نيل كلينك" before "مركز النيل" —
     * and falls back to the name so the rest of the list is at least stable.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  callable(mixed): array<string, mixed>  $shape
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function group(string $type, Builder $query, int $perGroup, string $firstWord, string $nameExpression, callable $shape): array
    {
        $total = (clone $query)->count();

        $rows = $query
            ->orderByRaw("case when {$nameExpression} like ? then 0 else 1 end", [$firstWord.'%'])
            ->orderByRaw($nameExpression)
            ->limit($perGroup)
            ->get();

        return [
            'type' => $type,
            'total' => $total,
            'items' => array_values($rows->map($shape)->all()),
        ];
    }

    /**
     * How many facilities each place in the group actually holds.
     *
     * A facility counts where its own record sits in the place OR where any of
     * its branches does — the same rule the listing endpoint filters by, so the
     * number offered here is the number of cards the filter goes on to show.
     * One query for the whole group: a count per row would be twenty round
     * trips on a box somebody is still typing into.
     *
     * @param  array{type: string, total: int, items: list<array<string, mixed>>}  $group
     * @param  'city_id'|'governorate_id'  $column
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function withCounts(array $group, string $column): array
    {
        $ids = array_column($group['items'], 'id');

        if ($ids === []) {
            return $group;
        }

        $counts = DB::query()
            ->fromSub(
                Facility::query()
                    ->select('id as facility_id', $column)
                    ->whereIn($column, $ids)
                    ->unionAll(
                        FacilityBranch::query()
                            ->select('facility_id', $column)
                            ->whereIn($column, $ids)
                    ),
                'matched'
            )
            ->groupBy($column)
            ->pluck(DB::raw('count(distinct facility_id)'), $column);

        foreach ($group['items'] as $index => $item) {
            $group['items'][$index]['facility_count'] = (int) ($counts[$item['id']] ?? 0);
        }

        return $group;
    }

    /**
     * Whether a place has anything a visitor could walk into.
     *
     * A city or a governorate is only ever offered as a FILTER, so one holding
     * nothing is a suggestion whose only possible outcome is an empty grid —
     * the same rule the listing endpoint draws its city dropdown by. A facility
     * reaches a place through its own record or through any of its branches,
     * and either counts.
     *
     * Applied as part of the query rather than by dropping rows afterwards, so
     * the group's `total` counts what it is actually standing in front of.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $builder
     */
    private function hostsSomething(Builder $builder): void
    {
        $builder->whereHas('facilities')->orWhereHas('branches');
    }

    /**
     * Every word has to land somewhere, though not all in the same field.
     *
     * That is what makes a second word narrow rather than widen: "مركز
     * الزقازيق" should find the row whose name carries one and whose address
     * carries the other, and should not find every مركز in the country.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $builder
     * @param  list<string>  $words
     * @param  list<string>  $expressions  folded SQL, built by `DirectorySearch`
     */
    private function everyWord(Builder $builder, array $words, array $expressions): void
    {
        foreach ($words as $word) {
            $builder->where(function (Builder $any) use ($word, $expressions): void {
                foreach ($expressions as $expression) {
                    $any->orWhereRaw($expression.' like ?', ['%'.$word.'%']);
                }
            });
        }
    }

    /**
     * One translatable column, folded, in every language it may be written in.
     *
     * @return list<string>
     */
    private function translatedExpressions(string $column): array
    {
        return array_map(
            fn (string $locale): string => DirectorySearch::translated($column, $locale),
            self::LOCALES,
        );
    }
}
