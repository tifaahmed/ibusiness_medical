<?php

namespace App\Http\Controllers\Admin\Partner\Create;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPartnerCreateController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/Partner/Form/PartnerFormView');
    }
}
