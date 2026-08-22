<?php

namespace App\Http\Controllers\Admin\Role\Show;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Role\Show\AdminRoleShowResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminRoleShowController extends Controller
{
    public function __invoke(Request $request, Role $role): Response
    {
        $role->load('permissions');

        // Roles are attached through a morph table, so there is no users()
        // relation to count — read the pivot the same way the index does.
        $usersCount = DB::table(config('permission.table_names.model_has_roles'))
            ->where(config('permission.column_names.model_morph_key'), '!=', 0)
            ->where('model_type', User::class)
            ->where('role_id', $role->id)
            ->count();

        $role->setAttribute('users_count', $usersCount);

        return Inertia::render('Admin/Role/Show', [
            'role' => (new AdminRoleShowResource($role))->toArray($request),
        ]);
    }
}
