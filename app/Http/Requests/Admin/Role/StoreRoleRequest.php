<?php

namespace App\Http\Requests\Admin\Role;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Rules\PermissionsNotMutuallyExclusive;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRoleEnum::SUPER_ADMIN) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9_\\- ]+$/i',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
                Rule::notIn(UserRoleEnum::protectedRoles()),
            ],
            'permissions' => ['nullable', 'array', new PermissionsNotMutuallyExclusive()],
            'permissions.*' => ['string', Rule::in(UserPermissionEnum::all())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.not_in' => 'The role name is reserved.',
            'name.regex' => 'Role name may only contain letters, numbers, spaces, hyphens, and underscores.',
        ];
    }
}
