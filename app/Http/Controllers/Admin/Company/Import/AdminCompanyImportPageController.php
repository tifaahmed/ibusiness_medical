<?php

namespace App\Http\Controllers\Admin\Company\Import;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminCompanyImportPageController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Company/Import/CompanyImportView');
    }
}
