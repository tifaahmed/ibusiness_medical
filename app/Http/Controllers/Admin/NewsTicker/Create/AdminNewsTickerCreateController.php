<?php

namespace App\Http\Controllers\Admin\NewsTicker\Create;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminNewsTickerCreateController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/NewsTicker/Form/NewsTickerFormView');
    }
}
