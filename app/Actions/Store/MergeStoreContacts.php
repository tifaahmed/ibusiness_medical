<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\StoreBranch;
use App\Models\StoreManager;
use App\Support\StoreSpreadsheet;
use Illuminate\Support\Facades\Auth;

/**
 * The additive write behind both store imports (spreadsheet and package).
 *
 * Branches are matched by English name and contacts by name, within the store.
 * Whatever the source does not mention is left alone, and an empty value never
 * blanks a stored one. Phones arrive as spreadsheet-style text
 * ("0101… (phone), 022… (landline)") and are typed by {@see StoreSpreadsheet}.
 */
class MergeStoreContacts
{
    /** @param array<int, array<string, mixed>> $branches */
    public function branches(Store $store, array $branches): int
    {
        $existing = $store->branches()->get()->keyBy(fn ($b) => mb_strtolower(trim((string) $b->getTranslation('name', 'en', false))));
        $count = 0;

        foreach ($branches as $b) {
            $nameEn = trim((string) ($b['name'] ?? ''));
            $address = trim((string) ($b['address'] ?? ''));
            if ($nameEn === '' && $address === '') {
                continue;
            }
            $phones = StoreSpreadsheet::readPhones($b['phone'] ?? null);
            $area = array_filter((array) ($b['area'] ?? []), fn ($v) => trim((string) $v) !== '');

            $values = array_filter([
                'name' => $nameEn !== '' ? ['en' => $nameEn, 'ar' => trim((string) ($b['name_ar'] ?? '')) ?: $nameEn] : null,
                'address' => $address !== '' ? ['en' => $address, 'ar' => trim((string) ($b['address_ar'] ?? '')) ?: $address] : null,
                'area' => $area ?: null,
                'phone' => $phones ?: null,
                'governorate_id' => $b['governorate_id'] ?? null,
                'city_id' => $b['city_id'] ?? null,
                'latitude' => $b['latitude'] ?? null,
                'longitude' => $b['longitude'] ?? null,
                'google_location_url' => ($b['google_location_url'] ?? '') !== '' ? $b['google_location_url'] : null,
            ], fn ($v) => $v !== null);

            $key = mb_strtolower($nameEn);
            if ($key !== '' && isset($existing[$key])) {
                $existing[$key]->update($values);
            } else {
                StoreBranch::create($values + ['store_id' => $store->id, 'created_by' => Auth::id()]);
            }
            $count++;
        }

        return $count;
    }

    /** @param array<int, array<string, mixed>> $managers */
    public function managers(Store $store, array $managers): int
    {
        $existing = $store->managers()->get()->keyBy(fn ($m) => mb_strtolower(trim($m->name)));
        $count = 0;

        foreach ($managers as $m) {
            $name = trim((string) ($m['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $phones = StoreSpreadsheet::readPhones($m['phones'] ?? null);
            $position = trim((string) ($m['position'] ?? ''));

            $key = mb_strtolower($name);
            if (isset($existing[$key])) {
                $existing[$key]->update(array_filter([
                    'position' => $position !== '' ? $position : null,
                    'phones' => $phones ?: null,
                ], fn ($v) => $v !== null));
            } else {
                StoreManager::create([
                    'store_id' => $store->id,
                    'name' => $name,
                    'position' => $position !== '' ? $position : null,
                    'phones' => $phones,
                    'created_by' => Auth::id(),
                ]);
            }
            $count++;
        }

        return $count;
    }
}
