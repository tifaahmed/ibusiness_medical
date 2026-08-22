<?php

namespace App\Http\Resources\Admin\Role\Show;

use App\Enums\User\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRoleShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => UserRoleEnum::getLabelByName($this->name),
            'description' => UserRoleEnum::descriptionFor($this->name),
            'guard_name' => $this->guard_name,
            'permissions' => $this->permissions->pluck('name')->sort()->values()->all(),
            'users_count' => (int) ($this->users_count ?? 0),
            'is_protected' => UserRoleEnum::isProtected($this->name),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
