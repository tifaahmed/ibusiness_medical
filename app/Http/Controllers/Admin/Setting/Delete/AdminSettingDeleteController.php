<?php

namespace App\Http\Controllers\Admin\Setting\Delete;

use App\Http\Controllers\Admin\Setting\Actions\Concerns\HandlesSettingImages;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminSettingDeleteController extends BaseController
{
    use HandlesSettingImages;

    public function __invoke(int $setting): RedirectResponse
    {
        $setting = Setting::findOrFail($setting);

        try {
            // An uploaded image outlives its row otherwise; a bundled asset the
            // row merely pointed at is left where it is.
            if ($setting->value_type === Setting::TYPE_IMAGE) {
                $this->forgetImage($setting->value);
            }

            $setting->delete();
            Log::info('Setting deleted', ['id' => $setting->id, 'slug' => $setting->slug]);

            return redirect()->route('admin.setting.list')->with('success', 'Setting deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete setting', ['error' => $e->getMessage()]);

            return back()->withErrors(['error' => 'Failed to delete the setting. Please try again.']);
        }
    }
}
