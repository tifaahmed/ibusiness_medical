<?php

namespace App\Http\Controllers\Admin\Company\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Company\Actions\Update\UpdateCompanyAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Company\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminCompanyUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_COMPANIES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_COMPANIES; }

    public function __construct(private UpdateCompanyAction $updateAction) {}

    public function __invoke(UpdateCompanyRequest $request, string $company): RedirectResponse
    {
        try {
            $company = Company::where('slug', $company)->firstOrFail();
            $this->assertOwns($company);

            $updated = $this->updateAction->execute($company, $request->validated());
            Log::info('Company updated', ['id' => $updated->id, 'ip' => $request->ip()]);
            return redirect()->route('admin.company.list')->with('success', 'Company updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update company. Please try again.'])->withInput();
        }
    }
}
