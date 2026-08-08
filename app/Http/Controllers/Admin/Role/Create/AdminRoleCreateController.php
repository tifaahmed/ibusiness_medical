<?php

namespace App\Http\Controllers\Admin\Role\Create;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminRoleCreateController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Role/Create', [
            'all_permissions' => UserPermissionEnum::all(),
        ]);
    }
}
