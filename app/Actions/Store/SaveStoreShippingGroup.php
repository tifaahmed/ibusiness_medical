<?php

namespace App\Actions\Store;

use App\Models\ShippingGroup;
use App\Models\Store;

/**
 * Put a store in a shipping group (stores sharing a warehouse ship as one parcel at the group's price), create the
 * group on the spot when a new name is typed, and save the group's price / free-from figures. The group's figures are
 * shared: changing them here changes them for every store in the group.
 */
class SaveStoreShippingGroup
{
    /** @param array<string, mixed> $validated */
    public static function run(Store $store, array $validated): void
    {
        $name = trim((string) ($validated['new_shipping_group_name'] ?? ''));
        $id = $validated['shipping_group_id'] ?? null;
        $blank = fn ($v) => $v === null || $v === '' ? null : $v;

        if ($name !== '') {
            $group = ShippingGroup::query()->firstOrCreate(['name' => $name]);
        } else {
            $group = $id ? ShippingGroup::query()->find($id) : null;
        }

        if ($group !== null && (array_key_exists('group_price', $validated) || array_key_exists('group_free_threshold', $validated))) {
            $group->update([
                'price' => $blank($validated['group_price'] ?? null),
                'free_threshold' => $blank($validated['group_free_threshold'] ?? null),
            ]);
        }

        if ($store->shipping_group_id !== $group?->id) {
            $store->forceFill(['shipping_group_id' => $group?->id])->save();
        }
    }
}
