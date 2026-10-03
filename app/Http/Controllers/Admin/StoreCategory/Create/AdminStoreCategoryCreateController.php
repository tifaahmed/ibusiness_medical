<?php

namespace App\Http\Controllers\Admin\StoreCategory\Create;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreCategoryCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/StoreCategory/Create/StoreCategoryCreateView');
    }
}
