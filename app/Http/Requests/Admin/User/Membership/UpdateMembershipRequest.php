<?php

namespace App\Http\Requests\Admin\User\Membership;

use App\Enums\Address\AddressTypeEnum;
use App\Enums\Membership\PaymentTypeEnum;
use App\Enums\User\UserPermissionEnum;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateMembershipRequest extends FormRequest
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
        // Debug logging
        \Log::info('UpdateMembershipRequest - Before prepareForValidation', [
            'has_is_visible' => $this->has('is_visible'),
            'is_visible_value' => $this->input('is_visible'),
            'all_request_data' => $this->all(),
        ]);

        // Convert string boolean to actual boolean for is_active
        // If checkbox is unchecked, it won't be in the request, so default to false
        // If it's checked, it will be "1" or "true" as a string
        if ($this->has('is_active')) {
            $value = $this->input('is_active');
            if (is_string($value)) {
                $this->merge([
                    'is_active' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                ]);
            } elseif (is_bool($value)) {
                // Already a boolean, keep it as is
                $this->merge([
                    'is_active' => $value,
                ]);
            }
        } else {
            // Checkbox not in request means it's unchecked
            $this->merge([
                'is_active' => false,
            ]);
        }

        // Partner-locked admins cannot reassign the membership to another
        // partner — force the field to their own partner_id, ignoring
        // whatever came over the wire. Admins who also hold `manage own
        // memberships` keep free choice.
        if ($this->partnerLocked()) {
            $this->merge([
                'partner_id' => Auth::user()?->partner_id,
            ]);
        }

        // Convert string boolean to actual boolean for is_visible
        if ($this->has('is_visible')) {
            $value = $this->input('is_visible');
            if (is_bool($value)) {
                // Already a boolean, keep it as is
                $this->merge([
                    'is_visible' => $value,
                ]);
            } elseif (is_string($value)) {
                // Convert string to boolean
                $this->merge([
                    'is_visible' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                ]);
            } elseif (is_numeric($value)) {
                // Convert numeric (0/1) to boolean
                $this->merge([
                    'is_visible' => (bool) $value,
                ]);
            }
        } else {
            // Checkbox not in request means it's unchecked
            $this->merge([
                'is_visible' => false,
            ]);
        }

        // Convert string boolean to actual boolean for is_paid
        if ($this->has('is_paid')) {
            $value = $this->input('is_paid');
            if (is_bool($value)) {
                $this->merge(['is_paid' => $value]);
            } elseif (is_string($value)) {
                $this->merge([
                    'is_paid' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                ]);
            } elseif (is_numeric($value)) {
                $this->merge(['is_paid' => (bool) $value]);
            }
        } else {
            $this->merge(['is_paid' => false]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->getRouteUser();
        // Mirror the controller/action: pick the active membership first,
        // otherwise fall back to the user's first membership. We must ignore
        // the row being edited so the unique rule doesn't flag it against itself.
        $editingMembership = $user
            ? ($user->memberships()->where('is_active', true)->first()
                ?? $user->memberships()
                    ->orderBy('is_active', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first())
            : null;
        $membershipId = $editingMembership?->id;

        $membershipNumberRule = ['required', 'string'];

        if ($membershipId) {
            $membershipNumberRule[] = Rule::unique(Membership::class)->ignore($membershipId);
        } else {
            $membershipNumberRule[] = Rule::unique(Membership::class);
        }

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'phone' => ['required', 'string', 'regex:/^01\d{9}$/'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'membership_number' => $membershipNumberRule,
            'national_id' => 'required|string|digits:14',
            'registration_date' => 'nullable|date',
            'expiration_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $registrationDate = $this->input('registration_date');
                    if ($registrationDate && $value && strtotime($value) <= strtotime($registrationDate)) {
                        $fail('The expiration date must be after the registration date.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            'payment_type' => ['nullable', 'string', Rule::in(PaymentTypeEnum::values())],
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
            'initial_payment_amount' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($this->requiresInitialPayment($editingMembership) && $this->input('initial_payment_type') !== 'free')],
            'initial_payment_type' => ['nullable', 'string', Rule::in(['commission', 'profit', 'free'])],
            'initial_payment_months_paid' => ['nullable', 'integer', 'min:1', Rule::requiredIf($this->requiresInitialPayment($editingMembership))],
            'initial_payment_from_date' => ['nullable', 'date', Rule::requiredIf($this->requiresInitialPayment($editingMembership))],
            'initial_payment_to_date' => ['nullable', 'date', 'after_or_equal:initial_payment_from_date', Rule::requiredIf($this->requiresInitialPayment($editingMembership))],
            'initial_payment_notes' => ['nullable', 'string', 'max:1000'],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
            'contract_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,avif|max:5120',
            'contract_image_remove' => 'nullable|boolean',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'image|mimes:jpeg,png,jpg,gif,webp,avif|max:5120',
            'gallery_remove_ids' => 'nullable|array',
            'gallery_remove_ids.*' => 'integer',
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
            'phone.regex' => 'The phone number must start with 01 and be exactly 11 digits.',
            'company_id.required' => 'Company is required for a paid monthly membership.',
            'sales_id.required' => 'Sales is required for a paid monthly membership.',
            'governorate_id.required' => 'The governorate field is required.',
            'initial_payment_amount.required' => 'Payment amount is required for the first payment.',
            'initial_payment_months_paid.required' => 'Months paid is required for the first payment.',
            'initial_payment_from_date.required' => 'Payment from date is required for the first payment.',
            'initial_payment_to_date.required' => 'Payment to date is required for the first payment.',
            'password.confirmed' => 'The password confirmation does not match.',
            'membership_number.required' => 'The membership number field is required.',
            'membership_number.unique' => 'This membership number is already in use.',
            'national_id.required' => 'The National ID number is required.',
            'national_id.digits' => 'The National ID number must be exactly 14 digits.',
            'expiration_date.required' => 'The expiration date field is required.',
            'expiration_date.after' => 'The expiration date must be after the registration date.',
            'is_active.boolean' => 'The active status must be either true or false.',
            'avatar.image' => 'The avatar must be an image.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, png, jpg, gif, avif.',
            'avatar.max' => 'The avatar may not be greater than 2MB.',
        ];
    }

    /**
     * Get the validated data from the request.
     * Override to ensure boolean fields are always included even when nullable.
     *
     * @param  array|int|string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Ensure is_visible is always included (from prepareForValidation)
        if (is_array($validated) && ! array_key_exists('is_visible', $validated)) {
            $validated['is_visible'] = $this->input('is_visible', false);
        }

        // Same guarantee for is_paid.
        if (is_array($validated) && ! array_key_exists('is_paid', $validated)) {
            $validated['is_paid'] = $this->input('is_paid', false);
        }

        return $validated;
    }

    /**
     * Get the user from the route parameter.
     */
    private function getRouteUser(): ?User
    {
        $userParam = $this->route('user');

        return $userParam instanceof User
            ? $userParam
            : ($userParam ? User::where('slug', $userParam)->first() : null);
    }

    /**
     * Phone, Company, Sales, and Governorate are required when the membership
     * is paid with a monthly payment type.
     */
    private function requiresPaidMonthlyFields(): bool
    {
        return $this->boolean('is_paid') && $this->input('payment_type') === PaymentTypeEnum::MONTHLY->value;
    }

    /**
     * The first member-payment row is required when the admin marks a
     * never-paid membership as paid — mirrors the "Payment" card that appears
     * on the edit form under those same conditions. A membership that already
     * has payments is managed from the member-payment module instead.
     */
    private function requiresInitialPayment(?Membership $editingMembership): bool
    {
        if (! $this->boolean('is_paid')) {
            return false;
        }
        if ($editingMembership === null) {
            return true;
        }

        return ! $editingMembership->memberPayments()->exists();
    }

    /**
     * True when partner is the ONLY narrowing scope. Mixed-scope admins
     * (also holding `manage own memberships`) and full-access admins are
     * not locked.
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
}
