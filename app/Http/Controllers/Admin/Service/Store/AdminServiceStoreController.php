<?php

namespace App\Http\Controllers\Admin\Service\Store;

use App\Http\Controllers\Admin\Service\Actions\Store\StoreServiceAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Service\Store\StoreServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminServiceStoreController extends BaseController
{
    public function __construct(private StoreServiceAction $storeAction) {}

    public function __invoke(StoreServiceRequest $request): RedirectResponse
    {
        try {
            $service = $this->storeAction->execute($request->validated());
            Log::info('Service store request', ['id' => $service->id, 'ip' => $request->ip()]);
            return redirect()->route('admin.service.list')->with('success', 'Service created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create service. Please try again.'])->withInput();
        }
    }
}
