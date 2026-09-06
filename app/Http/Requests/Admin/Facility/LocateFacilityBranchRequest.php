<?php

namespace App\Http\Requests\Admin\Facility;

use Illuminate\Foundation\Http\FormRequest;

class LocateFacilityBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The branch modal sends whatever the admin has typed so far — nothing is
     * persisted here, it is only forwarded to the model as context. The branch
     * itself is saved by the admin afterwards, as with any other field.
     *
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
            'governorate' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.required' => 'Enter the branch address first — the AI needs it to find the location.',
        ];
    }

    /**
     * An address of empty strings is the same as no address: reject it here
     * rather than spending an AI call on a city-centre guess.
     */
    protected function prepareForValidation(): void
    {
        $address = array_filter(
            (array) $this->input('address', []),
            fn ($value) => is_string($value) && trim($value) !== ''
        );

        $this->merge(['address' => $address]);
    }
}
