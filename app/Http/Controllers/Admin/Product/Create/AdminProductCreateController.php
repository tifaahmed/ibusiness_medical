<?php

namespace App\Http\Controllers\Admin\Product\Create;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Product/Create/ProductCreateView');
    }
}
