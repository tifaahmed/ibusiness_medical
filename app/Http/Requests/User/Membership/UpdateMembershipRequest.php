<?php

namespace App\Http\Requests\User\Membership;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isActiveValue = $this->input('is_active');
        $isVisibleValue = $this->input('is_visible');

        $this->merge([
            'is_active' => $this->has('is_active')
                ? filter_var($isActiveValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
                : false,
            'is_visible' => $this->has('is_visible')
                ? filter_var($isVisibleValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
                : false,
        ]);
    }

    public function rules(): array
    {
        $user = $this->getRouteUser();
        $membershipId = $user?->memberships()->value('id');

        return [
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user?->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'membership_number' => ['required', 'string', Rule::unique(Membership::class)->ignore($membershipId)],
            'registration_date' => 'nullable|date',
            'expiration_date' => ['required', 'date', $this->expirationDateRule()],
            'is_active' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'job_title' => ['nullable', 'array'],
            'job_title.ar' => ['nullable', 'string', 'max:255'],
            'job_title.en' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,avif|max:2048',
        ];
    }

    private function getRouteUser(): ?User
    {
        $userParam = $this->route('user');
        return $userParam instanceof User 
            ? $userParam 
            : ($userParam ? User::where('slug', $userParam)->first() : null);
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
}

