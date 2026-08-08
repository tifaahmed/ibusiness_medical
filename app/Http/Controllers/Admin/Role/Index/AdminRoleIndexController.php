<?php

namespace App\Http\Controllers\Admin\Role\Index;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Role\RoleCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminRoleIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $roles = Role::with('permissions')
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $userCounts = DB::table(config('permission.table_names.model_has_roles'))
            ->where(config('permission.column_names.model_morph_key'), '!=', 0)
            ->where('model_type', User::class)
            ->whereIn('role_id', $roles->pluck('id'))
            ->select('role_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('role_id')
            ->pluck('cnt', 'role_id');

        $roles->getCollection()->transform(function (Role $role) use ($userCounts) {
            $role->setAttribute('users_count', (int) ($userCounts[$role->id] ?? 0));
            return $role;
        });

        return Inertia::render('Admin/Role/List', [
            'roles' => (new RoleCollection($roles))->toArray($request),
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
        ]);
    }
}
