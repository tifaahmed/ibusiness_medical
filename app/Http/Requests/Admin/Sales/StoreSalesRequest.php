<?php

namespace App\Http\Requests\Admin\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|array',
            'name.ar' => 'required|string|max:255',
            'name.en' => 'nullable|string|max:255',
            'admin_user_id' => [
                'nullable', 'integer', 'exists:users,id',
                \Illuminate\Validation\Rule::unique('sales', 'admin_user_id'),
                function ($attribute, $value, $fail) {
                    if ($value && ! \App\Models\User::whereKey($value)->whereHas('roles', fn ($q) => $q->where('name', '!=', \App\Enums\User\UserRoleEnum::MEMBER))->exists()) {
                        $fail('This account is not an admin.');
                    }
                },
            ],
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
        ];
    }
}
