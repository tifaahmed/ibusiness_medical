<?php

namespace App\Http\Controllers\Admin\Setting\Logo;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Setting;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Upload the logo drawn at the top of the admin sidebar.
 *
 * It writes the `deilar_logo` image setting (what HandleInertiaRequests shares
 * as `appLogo`). The previous file is left on the disk — nothing is deleted —
 * so an upload can always be undone by pointing the row back at it.
 */
class AdminSettingLogoController extends BaseController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            // No SVG: an uploaded SVG can carry script.
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.Setting::maxUploadKilobytes()],
        ]);

        try {
            $path = $request->file('logo')->store(Setting::IMAGE_DIRECTORY, 'public');
            SiteSettings::put('deilar_logo', $path, Setting::TYPE_IMAGE, ['ar' => 'شعار الموقع', 'en' => 'Site logo']);
        } catch (\Throwable $e) {
            Log::error('Logo upload failed', ['route' => 'admin.setting.logo', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return back()->with('error', 'Could not save the logo.');
        }

        return back()->with('success', 'Logo updated.');
    }
}
