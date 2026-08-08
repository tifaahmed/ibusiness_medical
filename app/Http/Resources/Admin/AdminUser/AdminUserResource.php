<?php

namespace App\Http\Resources\Admin\AdminUser;

use App\Enums\User\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authId = $request->user()?->id;
        $roles = $this->roles->pluck('name')->values()->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar ?? null,
            'roles' => $roles,
            'direct_permissions' => $this->permissions->pluck('name')->values()->all(),
            'is_super_admin' => in_array(UserRoleEnum::SUPER_ADMIN, $roles, true),
            'is_self' => $authId === $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
