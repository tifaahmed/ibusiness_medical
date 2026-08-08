<?php

namespace App\Http\Controllers\Admin\FacilityType\Create;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityTypeCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/FacilityType/Create/FacilityTypeCreateView');
    }
}



