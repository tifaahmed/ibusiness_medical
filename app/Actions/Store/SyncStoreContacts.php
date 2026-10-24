<?php

namespace App\Actions\Store;

use App\Models\Store;
use App\Models\StoreManager;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the contact side of a store form: its managers and its contract file.
 * Called inside the controller's transaction by both create and update, so the
 * two cannot drift apart.
 *
 * Managers the form no longer holds are SOFT-deleted (recoverable), never
 * dropped; a posted id that is not this store's is ignored.
 */
class SyncStoreContacts
{
    /** @param array<string, mixed> $validated */
    public function handle(Store $store, array $validated): void
    {
        if (array_key_exists('managers', $validated)) {
            $this->syncManagers($store, $validated['managers'] ?? []);
        }

        if (! empty($validated['contract'])) {
            $store->clearMediaCollection('contract');
            $store->addMedia($validated['contract'])->toMediaCollection('contract');
        } elseif (filter_var($validated['contract_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $store->clearMediaCollection('contract');
        }
    }

    /** @param array<int, array<string, mixed>> $managers */
    private function syncManagers(Store $store, array $managers): void
    {
        $keep = [];

        foreach ($managers as $data) {
            $values = [
                'name' => $data['name'],
                'position' => $data['position'] ?? null,
                'phones' => $data['phones'] ?? [],
            ];

            $existing = ! empty($data['id'])
                ? StoreManager::query()->where('store_id', $store->id)->find($data['id'])
                : null;

            if ($existing) {
                $existing->update($values);
                $keep[] = $existing->id;
            } else {
                $keep[] = StoreManager::create($values + ['store_id' => $store->id, 'created_by' => Auth::id()])->id;
            }
        }

        $store->managers()->whereNotIn('id', $keep)->update(['deleted_at' => now(), 'deleted_by' => Auth::id()]);
    }
}
