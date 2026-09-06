<?php

namespace App\Http\Controllers\Admin\Setting\Edit;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Setting\Show\AdminSettingShowResource;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingEditController extends BaseController
{
    public function __invoke(int $setting): Response
    {
        $setting = Setting::findOrFail($setting);

        return Inertia::render('Admin/Setting/Edit/SettingEditView', [
            'setting' => new AdminSettingShowResource($setting),
            'valueTypes' => Setting::VALUE_TYPES,
            // In megabytes, and never more than PHP here will accept.
            'maxImageSize' => round(Setting::maxUploadKilobytes() / 1024, 1),
        ]);
    }
}
