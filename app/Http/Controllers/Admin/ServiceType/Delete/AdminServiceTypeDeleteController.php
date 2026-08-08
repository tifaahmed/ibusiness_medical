<?php

namespace App\Http\Controllers\Admin\ServiceType\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\ServiceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminServiceTypeDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(int $serviceType): RedirectResponse
    {
        $serviceType = ServiceType::findOrFail($serviceType);
        $this->assertOwns($serviceType);

        try {
            $serviceType->delete();
            Log::info('ServiceType deleted', ['id' => $serviceType->id]);
            return redirect()->route('admin.service-type.list')->with('success', 'Service category deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete service category. Please try again.']);
        }
    }
}
