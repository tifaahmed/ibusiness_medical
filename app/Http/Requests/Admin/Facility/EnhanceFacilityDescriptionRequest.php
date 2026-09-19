<?php

namespace App\Http\Requests\Admin\Facility;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The facility form's "Enhance with AI" button on the description.
 *
 * Carries the description as typed so far — nothing is persisted, the improved
 * HTML is handed back into the open form.
 */
class EnhanceFacilityDescriptionRequest extends FormRequest
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
            'description' => ['required', 'array'],
            'description.ar' => ['nullable', 'string', 'max:30000'],
            'description.en' => ['nullable', 'string', 'max:30000'],
            'name' => ['nullable', 'array'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'facility_type' => ['nullable', 'string', 'max:255'],
        ];
    }
}
