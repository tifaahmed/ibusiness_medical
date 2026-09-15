<?php

namespace App\Http\Controllers\Admin\Governorate\Create;

use App\Http\Controllers\Controller as BaseController;
use App\Services\GovernorateEnglishBackfiller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminGovernorateCreateController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Governorate/Create/GovernorateCreateView', [
            'englishFixEnabled' => GovernorateEnglishBackfiller::isConfigured(),
        ]);
    }
}



