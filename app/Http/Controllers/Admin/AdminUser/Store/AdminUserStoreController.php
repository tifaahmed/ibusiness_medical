<?php

namespace App\Http\Controllers\Admin\AdminUser\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUser\StoreAdminUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class AdminUserStoreController extends Controller
{
    public function __invoke(StoreAdminUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $shouldBeVerified = array_key_exists('email_verified', $data)
            ? (bool) $data['email_verified']
            : true; // Preserve the previous default when the checkbox is omitted.

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'partner_id' => $data['partner_id'] ?? null,
            'email_verified_at' => $shouldBeVerified ? now() : null,
        ]);

        $user->syncRoles($data['roles']);
        $user->syncPermissions($data['permissions'] ?? []);

        return redirect()
            ->route('admin.admin-users.index')
            ->with('success', "Admin user {$user->email} created.");
    }
}
