<?php

namespace App\Http\Requests\Admin\AdminUser;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRoleEnum::SUPER_ADMIN) ?? false;
    }

    public function rules(): array
    {
        $assignableRoles = Role::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', [UserRoleEnum::SUPER_ADMIN, UserRoleEnum::MEMBER])
            ->pluck('name')
            ->values()
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($assignableRoles)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(UserPermissionEnum::all())],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'email_verified' => ['nullable', 'boolean'],
        ];
    }
}
