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
            'phone' => ['nullable', 'string', 'regex:/^01\d{9}$/', Rule::unique(User::class, 'phone')->whereNull('deleted_at')],
            'membership_number' => 'required|string|digits:6|unique:'.Membership::class.',membership_number',
            // A trial card may be made without a National ID (it can be added later).
            'national_id' => [$this->boolean('is_trial') ? 'nullable' : 'required', 'string', 'digits:14', Rule::unique(Membership::class, 'national_id')->whereNull('deleted_at')],
            'registration_date' => 'required|date',
            // Expiration Date field hidden from the create/edit UI — no longer required from the admin.
            // 'expiration_date' => 'required|date|after:registration_date',
            'expiration_date' => 'nullable|date|after:registration_date',
            'is_active' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            // A free trial card (plan = trial). No expiry typed = today + the admin's trial days.
            'is_trial' => ['nullable', 'boolean'],
            // A trial must say the day it ends; it can be the membership's expiry or earlier (checked in withValidator).
            'trial_ends_at' => [Rule::requiredIf(fn () => $this->boolean('is_trial')), 'nullable', 'date'],
            // A fixed login code for THIS member (super admin only, stored hashed, optional expiry).
            'fixed_code' => ['nullable', 'string', 'regex:/^\d{4,8}$/'],
            'fixed_code_expires_at' => ['nullable', 'date', 'after:now'],
            'payment_type' => ['nullable', 'string', Rule::in(PaymentTypeEnum::values())],
            // Individual or company: the admin must say which, a new member never defaults silently.
            'member_kind' => ['required', 'string', Rule::in(Membership::kinds())],
            'job_title' => ['nullable', 'array'],
            'job_title.ar' => ['nullable', 'string', 'max:255'],
            'job_title.en' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif',
            'contract_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,avif',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp,avif',
            'family_members' => 'nullable|array',
            'family_members.*.name' => 'required|string|max:255',
            'family_members.*.relationship' => ['required', Rule::in(RelationshipEnum::values())],
            'family_members.*.date_of_birth' => 'nullable|date|before:today.*.phone,family_members.*.national_id',
            'family_members.*.phone' => 'nullable|string|max:20.*.date_of_birth,family_members.*.national_id',
            'family_members.*.national_id' => 'nullable|digits:14.*.phone,family_members.*.date_of_birth',
            'family_members.*.email' => 'nullable|email|max:255',
            'family_members.*.is_active' => 'nullable|boolean',
            'family_members.*.photo_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    /** A member who skips the SMS code is a hole in the login: only the super admin may open it. */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->filled('fixed_code') && ! $this->user()?->hasRole(\App\Enums\User\UserRoleEnum::SUPER_ADMIN)) {
                $v->errors()->add('fixed_code', 'Only the super admin can set a member login code.');
            }
            if ($this->boolean('is_trial') && $this->filled('trial_ends_at') && $this->filled('expiration_date')
                && \Illuminate\Support\Carbon::parse($this->input('trial_ends_at'))->gt(\Illuminate\Support\Carbon::parse($this->input('expiration_date')))) {
                $v->errors()->add('trial_ends_at', __('admin.member.trial_ends_after_expiry'));
            }
            // A company subscribes monthly only; individuals may pick monthly, half-yearly or yearly.
            if ($this->input('member_kind') === Membership::KIND_COMPANY && $this->boolean('is_paid')
                && $this->filled('payment_type') && $this->input('payment_type') !== PaymentTypeEnum::MONTHLY->value) {
                $v->errors()->add('payment_type', __('admin.member.payment_type_company_monthly'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone.regex' => 'The phone number must start with 01 and be exactly 11 digits.',
            'membership_number.required' => 'The membership number field is required.',
            'membership_number.unique' => 'This membership number is already in use.',
            'membership_number.digits' => 'The membership number must be exactly 6 digits.',
            'phone.unique' => 'This phone number already belongs to another member.',
            'national_id.unique' => 'This National ID already belongs to another card.',
            'national_id.required' => 'The National ID number is required.',
            'national_id.digits' => 'The National ID number must be exactly 14 digits.',
            'registration_date.required' => 'The registration date field is required.',
            'expiration_date.required' => 'The expiration date field is required.',
            'expiration_date.after' => 'The expiration date must be after the registration date.',
            'is_active.boolean' => 'The active status must be either true or false.',
        ];
    }
}
