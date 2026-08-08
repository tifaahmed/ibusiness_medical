<?php

namespace App\Http\Controllers\Admin\Governorate\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Governorate\Actions\Update\UpdateGovernorateAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Governorate\UpdateGovernorateRequest;
use App\Models\Governorate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminGovernorateUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_GOVERNORATES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_GOVERNORATES; }

    private UpdateGovernorateAction $updateAction;

    public function __construct(UpdateGovernorateAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    /**
     * Update the specified governorate.
     */
    public function __invoke(
        UpdateGovernorateRequest $request,
        string $governorate,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $governorate = Governorate::where('slug', $governorate)->firstOrFail();
            $this->assertOwns($governorate);

            // Execute the action to update governorate in database
            $updatedGovernorate = $this->updateAction->execute($governorate, $validated);

            Log::info('Governorate updated successfully', [
                'governorate_id' => $updatedGovernorate->id,
                'governorate_slug' => $updatedGovernorate->slug,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.governorate.list')
                ->with('success', 'Governorate updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update governorate', [
                'governorate_slug' => $governorate,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update governorate. Please try again.'])
                ->withInput();
        }
    }
}



