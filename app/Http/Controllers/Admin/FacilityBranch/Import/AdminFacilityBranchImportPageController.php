<?php

namespace App\Http\Controllers\Admin\FacilityBranch\Import;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchImportPageController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/FacilityBranch/Import/FacilityBranchImportView');
    }
}
