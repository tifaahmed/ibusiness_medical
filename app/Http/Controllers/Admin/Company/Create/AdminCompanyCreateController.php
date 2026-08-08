<?php

namespace App\Http\Controllers\Admin\Company\Create;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminCompanyCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Company/Create/CompanyCreateView');
    }
}
