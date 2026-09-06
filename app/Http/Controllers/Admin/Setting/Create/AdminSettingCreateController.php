<?php

namespace App\Http\Controllers\Admin\Setting\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Setting/Create/SettingCreateView', [
            'valueTypes' => Setting::VALUE_TYPES,
            // In megabytes, and never more than PHP here will accept.
            'maxImageSize' => round(Setting::maxUploadKilobytes() / 1024, 1),
        ]);
    }
}
