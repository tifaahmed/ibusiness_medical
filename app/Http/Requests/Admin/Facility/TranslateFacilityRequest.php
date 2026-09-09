<?php

namespace App\Http\Requests\Admin\Facility;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The facility form's "Fix English fields with AI" button on the create page.
 *
 * The edit page's button works on the saved row; this one carries the boxes as
 * they stand — facility and the branches typed into the form but not written
 * yet — because on create there is nothing on disk to read. Nothing is
 * persisted: the English values are handed back into the open form.
 */
class TranslateFacilityRequest extends FormRequest
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
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'facility_type' => ['nullable', 'string', 'max:255'],

            'branches' => ['nullable', 'array', 'max:50'],
            'branches.*.name' => ['nullable', 'array'],
            'branches.*.name.ar' => ['nullable', 'string', 'max:255'],
            'branches.*.name.en' => ['nullable', 'string', 'max:255'],
            'branches.*.address' => ['nullable', 'array'],
            'branches.*.address.ar' => ['nullable', 'string', 'max:1000'],
            'branches.*.address.en' => ['nullable', 'string', 'max:1000'],
            'branches.*.governorate' => ['nullable', 'string', 'max:255'],
            'branches.*.city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
