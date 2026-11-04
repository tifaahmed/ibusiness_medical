<?php

namespace App\Http\Controllers\Admin\Setting\Show;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Setting\Show\AdminSettingShowResource;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingShowController extends BaseController
{
    public function __invoke(int $setting): Response
    {
        $setting = Setting::findOrFail($setting);
        abort_unless(\App\Support\SettingAccess::seesAllRows(request()->user()) || in_array($setting->slug, \App\Support\SettingAccess::editableTemplateSlugs(request()->user()), true), 403);

        return Inertia::render('Admin/Setting/Show', [
            'setting' => new AdminSettingShowResource($setting),
        ]);
    }
}
