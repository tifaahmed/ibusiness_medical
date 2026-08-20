<?php

namespace App\Http\Controllers\Admin\ProductType\Create;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductTypeCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/ProductType/Create/ProductTypeCreateView');
    }
}
