<?php

namespace App\Http\Controllers\Admin\Service\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminServiceDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(string $service): RedirectResponse
    {
        $service = Service::where('slug', $service)->firstOrFail();
        $this->assertOwns($service);

        try {
            $service->delete();
            Log::info('Service deleted', ['id' => $service->id]);
            return redirect()->route('admin.service.list')->with('success', 'Service deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete service. Please try again.']);
        }
    }
}
