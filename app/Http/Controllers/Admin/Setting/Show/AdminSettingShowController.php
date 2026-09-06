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

        return Inertia::render('Admin/Setting/Show', [
            'setting' => new AdminSettingShowResource($setting),
        ]);
    }
}
