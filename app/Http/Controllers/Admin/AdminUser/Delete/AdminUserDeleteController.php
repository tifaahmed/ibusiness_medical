<?php

namespace App\Http\Controllers\Admin\AdminUser\Delete;

use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminUserDeleteController extends Controller
{
    public function __invoke(Request $request, User $adminUser): RedirectResponse
    {
        if ($request->user()->id === $adminUser->id) {
            return back()->withErrors(['admin' => 'You cannot delete your own account.']);
        }

        if ($adminUser->hasRole(UserRoleEnum::SUPER_ADMIN)) {
            return back()->withErrors(['admin' => 'Super admin users cannot be deleted from this UI.']);
        }

        $email = $adminUser->email;
        $adminUser->delete();

        return redirect()
            ->route('admin.admin-users.index')
            ->with('success', "Admin user {$email} deleted.");
    }
}
