<?php

namespace App\Http\Controllers\Admin\Store\Import;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreImportPageController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Store/Import/StoreImportView');
    }
}
