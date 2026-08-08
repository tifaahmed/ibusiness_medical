<?php

namespace App\Http\Controllers\Admin\Governorate\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Governorate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminGovernorateDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_GOVERNORATES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_GOVERNORATES; }

    /**
     * Remove the specified governorate from storage.
     */
    public function __invoke(Request $request, string $governorateSlug): RedirectResponse
    {
        try {
            $governorate = Governorate::where('slug', $governorateSlug)->firstOrFail();
            $this->assertOwns($governorate);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Governorate not found for deletion', [
                'slug' => $governorateSlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Governorate not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching governorate for deletion', [
                'slug' => $governorateSlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the governorate.']);
        }

        // Store data for logging before deletion
        $governorateId = $governorate->id;
        $governorateName = $governorate->name;
        $governorateSlugValue = $governorate->slug;
        $facilitiesCount = $governorate->facilities()->count();

        // Check if governorate has facilities
        if ($facilitiesCount > 0) {
            return back()->withErrors(['error' => 'Cannot delete governorate with associated facilities. Please remove or reassign facilities first.']);
        }

        try {
            DB::beginTransaction();

            // Delete the governorate
            $governorate->delete();

            DB::commit();

            Log::info('Governorate deleted successfully', [
                'governorate_id' => $governorateId,
                'governorate_slug' => $governorateSlugValue,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.governorate.list')
                ->with('success', 'Governorate deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete governorate', [
                'governorate_id' => $governorateId,
                'governorate_slug' => $governorateSlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete governorate. Please try again.']);
        }
    }
}



