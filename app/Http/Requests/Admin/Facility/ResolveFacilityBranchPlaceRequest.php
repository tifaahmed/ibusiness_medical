<?php

namespace App\Http\Requests\Admin\Facility;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The branch form's "Fill governorate & city from the address" button.
 *
 * Carries whatever the admin has typed so far — nothing is persisted, the ids
 * are handed back into the open form for them to check.
 */
class ResolveFacilityBranchPlaceRequest extends FormRequest
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
            'address' => ['required', 'array'],
            'address.ar' => ['nullable', 'string', 'max:1000'],
            'address.en' => ['nullable', 'string', 'max:1000'],
            'name' => ['nullable', 'array'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'facility_name' => ['nullable', 'array'],
            'facility_name.ar' => ['nullable', 'string', 'max:255'],
            'facility_name.en' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'Enter the branch address first — it is what the place is read from.',
        ];
    }
}
