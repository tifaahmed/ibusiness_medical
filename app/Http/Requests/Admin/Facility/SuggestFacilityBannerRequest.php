<?php

namespace App\Http\Requests\Admin\Facility;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The facility form's "Suggest with AI" button on the banner card.
 *
 * Carries the form as typed so far — nothing is persisted, the suggestion is
 * handed back into the open form.
 */
class SuggestFacilityBannerRequest extends FormRequest
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
            'facility_type' => ['nullable', 'string', 'max:255'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:30000'],
            'description.en' => ['nullable', 'string', 'max:30000'],
            'current' => ['nullable', 'array'],
            'current.ar' => ['nullable', 'string', 'max:255'],
            'current.en' => ['nullable', 'string', 'max:255'],
        ];
    }
}
