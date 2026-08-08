<?php

namespace App\Http\Controllers\Admin\Contract\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Contract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminContractDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_CONTRACTS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_CONTRACTS; }

    /**
     * Remove the specified contract from storage.
     */
    public function __invoke(Request $request, string $contractSlug): RedirectResponse
    {
        try {
            $contract = Contract::where('slug', $contractSlug)->firstOrFail();
            $this->assertOwns($contract);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Contract not found for deletion', [
                'slug' => $contractSlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Contract not found.']);
        } catch (\Exception $e) {
            Log::error('Error fetching contract for deletion', [
                'slug' => $contractSlug,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'An error occurred while fetching the contract.']);
        }

        $contractId = $contract->id;
        $contractName = $contract->name;
        $contractSlugValue = $contract->slug;

        try {
            DB::beginTransaction();

            $contract->delete();

            DB::commit();

            Log::info('Contract deleted successfully', [
                'contract_id' => $contractId,
                'contract_slug' => $contractSlugValue,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.contract.list')
                ->with('success', 'Contract deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete contract', [
                'contract_id' => $contractId,
                'contract_slug' => $contractSlugValue,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete contract. Please try again.']);
        }
    }
}
