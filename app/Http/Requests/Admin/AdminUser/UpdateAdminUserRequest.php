<?php

namespace App\Http\Requests\Admin\AdminUser;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission-only: whoever holds `manage users` or `manage admin users`
        // (the same pair guarding the routes) may submit this form, regardless
        // of which role carries it.
        return $this->user()?->hasAnyPermission([
            UserPermissionEnum::MANAGE_USERS,
            UserPermissionEnum::MANAGE_ADMIN_USERS,
        ]) ?? false;
    }

    public function rules(): array
    {
        $adminUser = $this->route('adminUser');

        $assignableRoles = Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', [UserRoleEnum::SUPER_ADMIN, UserRoleEnum::MEMBER])
            ->pluck('name')
            ->values()
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($adminUser?->id)->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($assignableRoles)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(UserPermissionEnum::all())],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'email_verified' => ['nullable', 'boolean'],
        ];
    }
}
