<?php

namespace App\Http\Requests\Admin\FacilityBranch;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The branch form's "Fix languages with AI" button.
 *
 * Carries whatever the admin has typed so far in both languages — nothing is
 * persisted, the corrected values are handed back into the open form.
 */
class FixBranchLanguagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'array'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'array'],
            'address.ar' => ['nullable', 'string', 'max:1000'],
            'address.en' => ['nullable', 'string', 'max:1000'],
            'facility_name' => ['nullable', 'string', 'max:255'],
            'facility_type' => ['nullable', 'string', 'max:255'],
            'governorate' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
