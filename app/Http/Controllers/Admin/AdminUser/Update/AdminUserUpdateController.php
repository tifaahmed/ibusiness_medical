<?php

namespace App\Http\Controllers\Admin\AdminUser\Update;

use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUser\UpdateAdminUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminUserUpdateController extends Controller
{
    public function __invoke(UpdateAdminUserRequest $request, User $adminUser): RedirectResponse
    {
        if ($adminUser->hasRole(UserRoleEnum::SUPER_ADMIN)) {
            throw new NotFoundHttpException('Super admin users cannot be updated from this UI.');
        }

        $data = $request->validated();

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'partner_id' => $data['partner_id'] ?? null,
        ];
        if (! empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        // Verified flag: write a timestamp when first set, clear when unset,
        // and leave it alone otherwise so we don't keep bumping the value on
        // every save.
        if (array_key_exists('email_verified', $data)) {
            $shouldBeVerified = (bool) $data['email_verified'];
            $isVerified = $adminUser->email_verified_at !== null;
            if ($shouldBeVerified && !$isVerified) {
                $update['email_verified_at'] = now();
            } elseif (!$shouldBeVerified && $isVerified) {
                $update['email_verified_at'] = null;
            }
        }

        $adminUser->update($update);

        $adminUser->syncRoles($data['roles']);
        $adminUser->syncPermissions($data['permissions'] ?? []);

        return redirect()
            ->route('admin.admin-users.index')
            ->with('success', "Admin user {$adminUser->email} updated.");
    }
}
