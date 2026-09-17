<?php

namespace App\Http\Controllers\Admin\Store\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminStoreDeleteController extends BaseController
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

    public function __invoke(Store $store): RedirectResponse
    {
        $this->assertOwns($store);

        if ($store->products()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete a store with products still assigned to it. Please reassign or remove its products first.']);
        }

        try {
            $storeId = $store->id;

            foreach ($store->galleries as $item) {
                Storage::disk('public')->delete($item->media_path);
            }

            $store->delete();

            Log::info('Store deleted successfully', ['store_id' => $storeId]);

            return redirect()->route('admin.store.list')
                ->with('success', 'Store deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete store', [
                'store_id' => $store->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete store. Please try again.']);
        }
    }
}
