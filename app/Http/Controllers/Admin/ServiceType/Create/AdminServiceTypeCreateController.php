<?php

namespace App\Http\Controllers\Admin\ServiceType\Create;

use App\Enums\ServiceType\ServiceTypeEnum;
use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceTypeCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/ServiceType/Create/ServiceTypeCreateView', [
            'iconOptions' => ServiceTypeEnum::getIconOptions(),
            'colorOptions' => ServiceTypeEnum::getColorOptions(),
        ]);
    }
}
