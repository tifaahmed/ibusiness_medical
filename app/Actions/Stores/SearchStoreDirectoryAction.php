<?php

namespace App\Actions\Stores;

use App\Models\Store;
use App\Models\StoreBranch;
use App\Support\DirectorySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;

/**
 * One typed phrase, answered across the stores directory — trimmed version of
 * `App\Actions\Facilities\SearchDirectoryAction` for a domain with no type,
 * tags or head-office address of its own: only two groups, `store` and
 * `branch`, rather than five. See that class for the reasoning behind
 * grouping by kind and folding both locales.
 */
class SearchStoreDirectoryAction
{
    public const PER_GROUP = 20;

    public const MAX_PER_GROUP = 50;

    public const MIN_TERM = 2;

    private const MIN_PHONE_DIGITS = 3;

    private const LOCALES = ['ar', 'en'];

    /**
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
            $this->stores($words, $perGroup),
            $this->branches($words, DirectorySearch::digits($term), $perGroup),
        ];

        return [
            'query' => $term,
            'groups' => array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== [])),
        ];
    }

    /**
     * Stores matched on their title or slug.
     *
     * @param  list<string>  $words
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function stores(array $words, int $perGroup): array
    {
        $query = Store::query()
            ->with(['media'])
            ->where(function (Builder $builder) use ($words): void {
                $this->everyWord($builder, $words, [
                    ...$this->translatedExpressions('title'),
                    DirectorySearch::plain('slug'),
                ]);
            });

        return $this->group('store', $query, $perGroup, $words[0], DirectorySearch::translated('title', App::getLocale()), fn (Store $store): array => [
            'id' => $store->id,
            'slug' => $store->slug,
            'title' => $store->title,
            'logo' => $store->logo ?: null,
        ]);
    }

    /**
     * Branches matched on their name, address, or a phone number.
     *
     * @param  list<string>  $words
     * @return array{type: string, total: int, items: list<array<string, mixed>>}
     */
    private function branches(array $words, string $digits, int $perGroup): array
    {
        $query = StoreBranch::query()
            ->with(['store.media', 'city', 'governorate'])
            ->whereHas('store')
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

        return $this->group('branch', $query, $perGroup, $words[0], DirectorySearch::translated('name', App::getLocale()), fn (StoreBranch $branch): array => [
            'id' => $branch->id,
            'name' => $branch->name,
            'address' => $branch->address,
            'phone' => $branch->phoneNumbers(),
            'city' => $branch->city?->name,
            'governorate' => $branch->governorate?->name,
            'store_id' => $branch->store_id,
            'store_title' => $branch->store?->title,
            'store_slug' => $branch->store?->slug,
            'logo' => $branch->store?->logo ?: null,
        ]);
    }

    /**
     * Count, order and shape one group — identical mechanics to
     * `SearchDirectoryAction::group()`.
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
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $builder
     * @param  list<string>  $words
     * @param  list<string>  $expressions
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
