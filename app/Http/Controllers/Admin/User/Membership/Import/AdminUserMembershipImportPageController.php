<?php

namespace App\Http\Controllers\Admin\User\Membership\Import;

use App\Http\Controllers\Controller as BaseController;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipImportPageController extends BaseController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/User/Member/Import/MemberImportView');
    }
}
