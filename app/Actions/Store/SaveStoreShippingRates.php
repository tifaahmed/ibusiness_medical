<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\StoreShippingRate;

/**
 * Make a store's per-governorate shipping overrides match what the admin form
 * posted. A row with neither a price nor a threshold overrides nothing and is
 * not kept; rows the form no longer lists are removed (they are only ever
 * created from this form).
 */
class SaveStoreShippingRates
{
    /** @param array<int, array{governorate_id: int|string, price?: mixed, free_threshold?: mixed}> $rows */
    public static function run(Store $store, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $price = ($row['price'] ?? '') === '' ? null : $row['price'];
            $free = ($row['free_threshold'] ?? '') === '' ? null : $row['free_threshold'];

            if ($price === null && $free === null) {
                continue;
            }

            StoreShippingRate::query()->updateOrCreate(
                ['store_id' => $store->id, 'governorate_id' => (int) $row['governorate_id']],
                ['price' => $price, 'free_threshold' => $free],
            );
            $keep[] = (int) $row['governorate_id'];
        }

        $store->shippingRates()->whereNotIn('governorate_id', $keep)->delete();
    }
}
