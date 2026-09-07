<?php

namespace App\Http\Requests\Admin\Facility;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The branch modal's "Fix English with AI" button.
 *
 * Like the locate request, this carries whatever the admin has typed so far —
 * nothing is persisted, the English values are handed back into the open form.
 */
class TranslateFacilityBranchRequest extends FormRequest
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
            'address' => ['nullable', 'array'],
            'address.ar' => ['nullable', 'string', 'max:1000'],
            'facility_name' => ['nullable', 'string', 'max:255'],
            'facility_type' => ['nullable', 'string', 'max:255'],
            'governorate' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
