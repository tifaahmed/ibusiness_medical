<?php

namespace App\Http\Requests\Admin\Facility;

use Illuminate\Foundation\Http\FormRequest;

class GenerateFacilitySeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The form sends whatever the admin has typed so far — nothing is persisted
     * here, it is only forwarded to the model as context.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:5000'],
            'description.en' => ['nullable', 'string', 'max:5000'],
            'facility_type' => ['nullable', 'string', 'max:255'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'governorates' => ['nullable', 'array', 'max:50'],
            'governorates.*' => ['nullable', 'string', 'max:255'],
            'cities' => ['nullable', 'array', 'max:100'],
            'cities.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter the facility name first — the AI needs it to write the metadata.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // A facility with no name at all gives the model nothing to work with.
        $name = (array) $this->input('name', []);

        $this->merge([
            'name' => array_filter($name, fn ($value) => is_string($value)),
        ]);
    }
}
