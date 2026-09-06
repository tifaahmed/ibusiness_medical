<?php

namespace App\Http\Controllers\Admin\Setting\Update;

use App\Http\Controllers\Admin\Setting\Actions\Update\UpdateSettingAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Setting\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminSettingUpdateController extends BaseController
{
    public function __construct(private UpdateSettingAction $updateAction) {}

    public function __invoke(UpdateSettingRequest $request, int $setting): RedirectResponse
    {
        try {
            $setting = Setting::findOrFail($setting);
            $updated = $this->updateAction->execute($setting, $request->validated());
            Log::info('Setting updated', ['id' => $updated->id, 'slug' => $updated->slug, 'ip' => $request->ip()]);

            return redirect()->route('admin.setting.list')->with('success', 'Setting updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update setting', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to update the setting. Please try again.'])->withInput();
        }
    }
}
