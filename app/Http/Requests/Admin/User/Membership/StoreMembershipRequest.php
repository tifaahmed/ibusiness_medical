<?php

namespace App\Http\Requests\Admin\User\Membership;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Enums\Membership\PaymentTypeEnum;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'email' => 'nullable|string|lowercase|email|max:255|unique:'.User::class.',email',
            'phone' => 'nullable|string|max:50',
            'membership_number' => 'required|string|unique:'.Membership::class.',membership_number',
            'national_id' => 'required|string|digits:14',
            'registration_date' => 'required|date',
            'expiration_date' => 'required|date|after:registration_date',
            'is_active' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            'payment_type' => ['nullable', 'string', Rule::in(PaymentTypeEnum::values())],
            'job_title' => ['nullable', 'array'],
            'job_title.ar' => ['nullable', 'string', 'max:255'],
            'job_title.en' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
            'contract_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,avif|max:5120',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp,avif|max:5120',
            'family_members' => 'nullable|array',
            'family_members.*.name' => 'required|string|max:255',
            'family_members.*.relationship' => ['required', Rule::in(RelationshipEnum::values())],
            'family_members.*.date_of_birth' => 'nullable|date|before:today',
            'family_members.*.phone' => 'nullable|string|max:20',
            'family_members.*.email' => 'nullable|email|max:255',
            'family_members.*.is_active' => 'nullable|boolean',
            'family_members.*.photo_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
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
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'membership_number.required' => 'The membership number field is required.',
            'membership_number.unique' => 'This membership number is already in use.',
            'national_id.required' => 'The National ID number is required.',
            'national_id.digits' => 'The National ID number must be exactly 14 digits.',
            'registration_date.required' => 'The registration date field is required.',
            'expiration_date.required' => 'The expiration date field is required.',
            'expiration_date.after' => 'The expiration date must be after the registration date.',
            'is_active.boolean' => 'The active status must be either true or false.',
        ];
    }
}

