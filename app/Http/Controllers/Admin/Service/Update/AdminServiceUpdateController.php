<?php

namespace App\Http\Controllers\Admin\Service\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Service\Actions\Update\UpdateServiceAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Service\Update\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminServiceUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __construct(private UpdateServiceAction $updateAction) {}

    public function __invoke(UpdateServiceRequest $request, string $service): RedirectResponse
    {
        try {
            $service = Service::where('slug', $service)->firstOrFail();
            $this->assertOwns($service);

            $updated = $this->updateAction->execute($service, $request->validated());
            Log::info('Service updated', ['id' => $updated->id, 'ip' => $request->ip()]);
            return redirect()->route('admin.service.list')->with('success', 'Service updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update service. Please try again.'])->withInput();
        }
    }
}
