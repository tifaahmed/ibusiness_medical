<?php

namespace App\Http\Resources\Admin\Role;

use App\Enums\User\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'permissions' => $this->permissions->pluck('name')->values()->all(),
            'users_count' => (int) ($this->users_count ?? 0),
            'is_protected' => UserRoleEnum::isProtected($this->name),
        ];
    }
}
