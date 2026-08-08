<?php

namespace App\Http\Controllers\Admin\ServiceType\Store;

use App\Http\Controllers\Admin\ServiceType\Actions\Store\StoreServiceTypeAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\ServiceType\StoreServiceTypeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminServiceTypeStoreController extends BaseController
{
    public function __construct(private StoreServiceTypeAction $storeAction) {}

    public function __invoke(StoreServiceTypeRequest $request): RedirectResponse
    {
        try {
            $serviceType = $this->storeAction->execute($request->validated());
            Log::info('ServiceType store request', ['id' => $serviceType->id, 'ip' => $request->ip()]);
            return redirect()->route('admin.service-type.list')->with('success', 'Service category created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create service category. Please try again.'])->withInput();
        }
    }
}
