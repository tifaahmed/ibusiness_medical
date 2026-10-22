<?php

namespace App\Actions\Visitor;

use App\Enums\Visitor\VisitSourceEnum;
use App\Models\Facility;
use App\Models\PageVisit;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * The numbers behind the visit charts — the Visitors page here and the
 * website's dashboard (`GET /api/v1/partner/visits/summary`), which used to
 * compute the same figures from its own `page_visits` (its `SummariseVisits`).
 * Same shape as that one, so the website's charts draw it unchanged.
 *
 * "Unique" is COUNT(DISTINCT visitor_id): a real device, not the website's old
 * day-salted hash. Per day it means the same as before; over the whole window
 * it is now a headcount of devices rather than a sum of daily uniques.
 * Imported history was rebuilt from ip + user agent, so there it is per
 * ip/browser pair.
 *
 * `source` narrows to one of web / android / ios, or `mobile` for both apps.
 */
class SummariseVisits
{
    public const DAY_OPTIONS = [7, 30, 90];

    public const DEFAULT_DAYS = 30;

    private const CACHE_SECONDS = 300;

    private const TOP_ITEMS = 20;

    /**
     * @return array{
     *     days: int, unique: bool, source: ?string, total: int,
     *     perDay: list<array{date: string, count: int}>,
 *     perDayBySource: array<string, list<array{date: string, count: int}>>,
     *     perPage: list<array{page: string, count: int}>,
     *     perSource: list<array{source: string, count: int}>,
     *     perProduct: list<array{id: int, slug: string, name: string, count: int}>,
     *     perFacility: list<array{id: int, slug: string, name: string, count: int}>,
     *     busiestDay: array{date: string, count: int}|null,
     * }
     */
    public function handle(int $days, bool $unique, ?string $source = null, ?string $locale = null): array
    {
        $days = in_array($days, self::DAY_OPTIONS, true) ? $days : self::DEFAULT_DAYS;
        $sources = VisitSourceEnum::forFilter($source);
        $source = $sources === null ? null : $source;
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : app()->getLocale();

        return Cache::remember(
            "visit-summary.{$days}.".($unique ? 'u' : 'a').'.'.($source ?? 'all').".{$locale}",
            self::CACHE_SECONDS,
            fn () => $this->build($days, $unique, $source, $sources, $locale),
        );
    }

    /** @param  array<int, string>|null  $sources */
    private function build(int $days, bool $unique, ?string $source, ?array $sources, string $locale): array
    {
        $since = Date::now()->subDays($days - 1)->startOfDay();
        $counted = $unique ? 'COUNT(DISTINCT visitor_id) as counted' : 'COUNT(*) as counted';
        $window = fn (): Builder => PageVisit::query()->fromPeople()->where('visited_at', '>=', $since)
            ->when($sources, fn (Builder $q) => $q->whereIn('source', $sources));

        $counts = $window()->selectRaw('DATE(visited_at) as day')->selectRaw($counted)
            ->groupBy('day')->pluck('counted', 'day')->map(fn ($n) => (int) $n);

        $perDay = collect(range(0, $days - 1))->map(function (int $offset) use ($since, $counts) {
            $date = $since->copy()->addDays($offset)->toDateString();

            return ['date' => $date, 'count' => $counts[$date] ?? 0];
        })->all();

        $busiest = collect($perDay)->sortByDesc('count')->first();

        // One line per source for the chart, so the website and each app can be read against each other.
        $bySource = $window()->select('source')->selectRaw('DATE(visited_at) as day')->selectRaw($counted)
            ->groupBy('source', 'day')->get()->groupBy('source');
        $perDayBySource = $bySource->map(fn ($rows) => collect($perDay)->map(fn ($p) => [
            'date' => $p['date'],
            'count' => (int) ($rows->firstWhere('day', $p['date'])?->counted ?? 0),
        ])->all())->all();

        return [
            'days' => $days,
            'unique' => $unique,
            'source' => $source,
            'total' => (int) $window()->selectRaw($counted)->value('counted'),
            'perDay' => $perDay,
            'perDayBySource' => $perDayBySource,
            'perPage' => $window()->select('page')->selectRaw($counted)->groupBy('page')->orderByDesc('counted')
                ->limit(50)->get()->map(fn ($r) => ['page' => $r->page, 'count' => (int) $r->counted])->all(),
            'perSource' => $window()->select('source')->selectRaw($counted)->groupBy('source')->orderByDesc('counted')
                ->get()->map(fn ($r) => ['source' => $r->source, 'count' => (int) $r->counted])->all(),
            'perProduct' => $this->topItems($window(), 'product_id', Product::class, $counted, $locale),
            'perFacility' => $this->topItems($window(), 'facility_id', Facility::class, $counted, $locale),
            'busiestDay' => ($busiest['count'] ?? 0) > 0 ? $busiest : null,
        ];
    }

    /**
     * @param  class-string<Product|Facility>  $model
     * @return list<array{id: int, slug: string, name: string, count: int}>
     */
    private function topItems(Builder $window, string $column, string $model, string $counted, string $locale): array
    {
        $rows = $window->whereNotNull($column)->select($column)->selectRaw($counted)
            ->groupBy($column)->orderByDesc('counted')->limit(self::TOP_ITEMS)->get();

        $query = $model::query();
        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed(); // a deleted facility still had its views
        }
        $items = $query->whereIn('id', $rows->pluck($column))->get(['id', 'slug', 'name'])->keyBy('id');

        return $rows->map(function ($r) use ($column, $items, $locale) {
            $item = $items->get($r->{$column});

            return [
                'id' => (int) $r->{$column},
                'slug' => (string) ($item?->slug ?? ''),
                'name' => $item ? (string) ($item->getTranslation('name', $locale, false) ?: $item->getTranslation('name', 'ar', false)) : '#'.$r->{$column},
                'count' => (int) $r->counted,
            ];
        })->values()->all();
    }
}
