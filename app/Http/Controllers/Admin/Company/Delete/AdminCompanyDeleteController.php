<?php

namespace App\Http\Controllers\Admin\Company\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminCompanyDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    /**
     * Remove the specified company from storage.
     */
    public function __invoke(Request $request, string $company): RedirectResponse
    {
        try {
            $companyModel = Company::where('slug', $company)->firstOrFail();
            $this->assertOwns($companyModel);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Company not found for deletion', [
                'slug' => $company,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Company not found.']);
        }

        $membershipsCount = $companyModel->memberships()->count();

        if ($membershipsCount > 0) {
            return back()->withErrors(['error' => 'Cannot delete a company with associated members. Please remove or reassign its members first.']);
        }

        try {
            $companyId = $companyModel->id;
            $companySlug = $companyModel->slug;

            $companyModel->delete();

            Log::info('Company deleted successfully', [
                'company_id' => $companyId,
                'company_slug' => $companySlug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.company.list')
                ->with('success', 'Company deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete company', [
                'slug' => $company,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete company. Please try again.']);
        }
    }
}
