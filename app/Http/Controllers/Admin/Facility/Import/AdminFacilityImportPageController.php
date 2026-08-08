<?php

namespace App\Http\Controllers\Admin\Facility\Import;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityImportPageController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Facility/Import/FacilityImportView');
    }
}
