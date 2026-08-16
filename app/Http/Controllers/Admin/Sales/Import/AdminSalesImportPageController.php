<?php

namespace App\Http\Controllers\Admin\Sales\Import;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminSalesImportPageController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Sales/Import/SalesImportView');
    }
}
