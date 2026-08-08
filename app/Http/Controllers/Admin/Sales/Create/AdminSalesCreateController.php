<?php

namespace App\Http\Controllers\Admin\Sales\Create;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSalesCreateController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/Sales/Form/SalesFormView');
    }
}
