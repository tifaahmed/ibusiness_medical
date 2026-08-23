<?php

namespace App\Http\Requests\User\Membership;

use App\Enums\Address\AddressTypeEnum;
use App\Enums\FamilyMember\RelationshipEnum;
use App\Enums\Membership\PaymentTypeEnum;
use App\Enums\User\UserPermissionEnum;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('is_active');
        $this->merge([
            'is_active' => $this->has('is_active')
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
                : false,
        ]);

        // Partner-locked admins cannot choose a different partner — overwrite
        // whatever they submit with their own partner_id so a tampered request
        // cannot bypass the lock in the UI. Admins who also hold `manage own
        // memberships` are not locked — they can pick any partner (or none)
        // since their creator scope still controls visibility.
        if ($this->partnerLocked()) {
            $this->merge([
                'partner_id' => Auth::user()?->partner_id,
            ]);
        }
    }

    /**
     * True when partner is the ONLY narrowing scope on the admin. Falls back
     * to false if they also hold `manage own memberships` (free choice) or
     * `manage memberships` (full access).
     */
    private function partnerLocked(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }
        if ($user->hasPermissionTo(UserPermissionEnum::MANAGE_MEMBERSHIPS)) {
            return false;
        }
        if ($user->hasPermissionTo(UserPermissionEnum::MANAGE_OWN_MEMBERSHIPS)) {
            return false;
        }
        return $user->hasPermissionTo(UserPermissionEnum::MANAGE_PARTNER_MEMBERSHIPS);
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
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => 'nullable|string', // Password is auto-generated, so it's optional
            'membership_number' => 'nullable|string|unique:'.Membership::class.',membership_number',
            'national_id' => 'required|string|digits:14',
            'registration_date' => 'required|date',
            'expiration_date' => ['required', 'date', $this->expirationDateRule()],
            'is_active' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'is_paid' => ['nullable', 'boolean'],
            'payment_type' => ['nullable', 'string', Rule::in(PaymentTypeEnum::values())],
            'initial_payment_amount' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($this->boolean('is_paid') && $this->input('initial_payment_type') !== 'free')],
            'initial_payment_type' => ['nullable', 'string', Rule::in(['commission', 'profit', 'free'])],
            'initial_payment_months_paid' => ['nullable', 'integer', 'min:1', Rule::requiredIf($this->boolean('is_paid'))],
            'initial_payment_from_date' => ['nullable', 'date', Rule::requiredIf($this->boolean('is_paid'))],
            'initial_payment_to_date' => ['nullable', 'date', 'after_or_equal:initial_payment_from_date', Rule::requiredIf($this->boolean('is_paid'))],
            'initial_payment_notes' => ['nullable', 'string', 'max:1000'],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
            'job_title' => ['nullable', 'array'],
            'job_title.ar' => ['nullable', 'string', 'max:255'],
            'job_title.en' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id', Rule::requiredIf($this->requiresPaidMonthlyFields())],
            'partner_id' => [
                $this->partnerLocked() ? 'required' : 'nullable',
                'integer',
                'exists:partners,id',
                ...($this->partnerLocked() && Auth::user()?->partner_id !== null
                    ? [Rule::in([(int) Auth::user()->partner_id])]
                    : []),
            ],
            'sales_id' => ['nullable', 'integer', 'exists:sales,id', Rule::requiredIf($this->requiresPaidMonthlyFields())],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            // The member's primary address. Only the governorate is required;
            // everything else is optional detail for the courier.
            'address_type' => ['nullable', 'string', Rule::in(AddressTypeEnum::values())],
            'address' => ['nullable', 'string', 'max:1000'],
            'street' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'apartment_number' => ['nullable', 'string', 'max:50'],
            'floor_number' => ['nullable', 'string', 'max:50'],
            'special_mark' => ['nullable', 'string', 'max:500'],
            'family_members' => ['nullable', 'array'],
            'family_members.*.name' => ['required', 'string', 'max:255'],
            'family_members.*.relationship' => ['required', 'string', Rule::in(RelationshipEnum::values())],
            'family_members.*.date_of_birth' => ['nullable', 'date'],
            'family_members.*.phone' => ['nullable', 'string', 'max:20'],
            'family_members.*.email' => ['nullable', 'string', 'email', 'max:255'],
            'family_members.*.is_active' => ['nullable', 'boolean'],
            'family_members.*.photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,avif', 'max:2048'],
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
            'phone.required' => 'The phone field is required.',
            'company_id.required' => 'Company is required for a paid monthly membership.',
            'sales_id.required' => 'Sales is required for a paid monthly membership.',
            'governorate_id.required' => 'The governorate field is required.',
            'initial_payment_amount.required' => 'Payment amount is required for a paid membership.',
            'initial_payment_months_paid.required' => 'Months paid is required for a paid membership.',
            'initial_payment_from_date.required' => 'Payment from date is required for a paid membership.',
            'initial_payment_to_date.required' => 'Payment to date is required for a paid membership.',
            'password.required' => 'The password field is required.',
            'password.confirmed' => 'The password confirmation does not match.',
            'membership_number.unique' => 'This membership number is already in use.',
            'national_id.required' => 'The National ID number is required.',
            'national_id.digits' => 'The National ID number must be exactly 14 digits.',
            'registration_date.required' => 'The registration date field is required.',
            'expiration_date.required' => 'The expiration date field is required.',
            'expiration_date.after' => 'The expiration date must be after the registration date.',
            'is_active.boolean' => 'The active status must be either true or false.',
            'avatar.image' => 'The avatar must be an image.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, png, jpg, gif, avif.',
            'avatar.max' => 'The avatar may not be greater than 2MB.',
        ];
    }

    private function expirationDateRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $registrationDate = $this->input('registration_date');
            if ($registrationDate && $value && strtotime($value) <= strtotime($registrationDate)) {
                $fail('The expiration date must be after the registration date.');
            }
        };
    }

    /**
     * Phone, Company, Sales, and Governorate are required when the membership
     * is paid with a monthly payment type.
     */
    private function requiresPaidMonthlyFields(): bool
    {
        return $this->boolean('is_paid') && $this->input('payment_type') === PaymentTypeEnum::MONTHLY->value;
    }
}

