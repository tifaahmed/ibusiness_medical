<?php

namespace App\Http\Controllers\Admin\ServiceType\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\ServiceType\Actions\Update\UpdateServiceTypeAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\ServiceType\UpdateServiceTypeRequest;
use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminServiceTypeUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __construct(private UpdateServiceTypeAction $updateAction) {}

    public function __invoke(UpdateServiceTypeRequest $request, int $serviceType): RedirectResponse
    {
        try {
            $serviceType = ServiceType::findOrFail($serviceType);
            $this->assertOwns($serviceType);

            $updated = $this->updateAction->execute($serviceType, $request->validated());
            Log::info('ServiceType updated', ['id' => $updated->id, 'ip' => $request->ip()]);
            return redirect()->route('admin.service-type.list')->with('success', 'Service category updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update service category. Please try again.'])->withInput();
        }
    }
}
