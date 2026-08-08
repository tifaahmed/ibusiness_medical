<?php

namespace App\Http\Controllers\Admin\MemberPayment\Import;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminMemberPaymentImportPageController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/MemberPayment/Import/MemberPaymentImportView');
    }
}
