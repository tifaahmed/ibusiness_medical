<?php

namespace App\Http\Requests\Admin\User\Membership\FamilyMember;

use App\Enums\FamilyMember\RelationshipEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFamilyMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string boolean to actual boolean for is_active
        if ($this->has('is_active')) {
            $value = $this->input('is_active');
            if (is_string($value)) {
                $this->merge([
                    'is_active' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                ]);
            } elseif (is_bool($value)) {
                $this->merge([
                    'is_active' => $value,
                ]);
            }
        } else {
            $this->merge([
                'is_active' => false,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'relationship' => ['required', Rule::enum(RelationshipEnum::class)],
            'date_of_birth' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'relationship.required' => 'The relationship field is required.',
            'relationship.enum' => 'The selected relationship is invalid.',
            'date_of_birth.date' => 'The date of birth must be a valid date.',
            'date_of_birth.before' => 'The date of birth must be before today.',
            'email.email' => 'The email must be a valid email address.',
            'photo.image' => 'The photo must be an image.',
            'photo.mimes' => 'The photo must be a file of type: jpeg, png, jpg, gif, avif.',
            'photo.max' => 'The photo may not be greater than 2MB.',
            'is_active.boolean' => 'The active status must be either true or false.',
        ];
    }
}

