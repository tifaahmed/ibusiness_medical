<?php

namespace App\Http\Controllers\Admin\Store\Manager;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Store;
use App\Models\StoreManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Puts back a contact removed from the store form. Called by the "Removed
 * contacts" list on the store edit page, which gets the restored contact back
 * immediately.
 */
class AdminStoreManagerRestoreController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_STORES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_STORES;
    }

    public function __invoke(Store $store, int $manager): JsonResponse
    {
        $this->assertOwns($store);

        $manager = StoreManager::onlyTrashed()
            ->where('id', $manager)
            ->where('store_id', $store->id)
            ->firstOrFail();

        try {
            $manager->deleted_by = null;
            $manager->restore();
        } catch (\Throwable $exception) {
            Log::error('Failed to restore store manager', [
                'store_id' => $store->id,
                'manager_id' => $manager->id,
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to restore the contact. Please try again.'], 500);
        }

        return response()->json([
            'message' => 'Contact restored successfully.',
            'manager' => [
                'id' => $manager->id,
                'name' => $manager->name,
                'position' => $manager->position,
                'phones' => $manager->phones ?? [],
            ],
        ]);
    }
}
