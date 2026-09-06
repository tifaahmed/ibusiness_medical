<?php

namespace App\Http\Controllers\Admin\Setting\Store;

use App\Http\Controllers\Admin\Setting\Actions\Store\StoreSettingAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Setting\StoreSettingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminSettingStoreController extends BaseController
{
    public function __construct(private StoreSettingAction $storeAction) {}

    public function __invoke(StoreSettingRequest $request): RedirectResponse
    {
        try {
            $setting = $this->storeAction->execute($request->validated());
            Log::info('Setting created', ['id' => $setting->id, 'slug' => $setting->slug, 'ip' => $request->ip()]);

            return redirect()->route('admin.setting.list')->with('success', 'Setting created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create setting', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to create the setting. Please try again.'])->withInput();
        }
    }
}
