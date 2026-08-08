<?php

namespace App\Http\Controllers\Admin\Faq\Create;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFaqCreateController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/Faq/Form/FaqFormView');
    }
}
