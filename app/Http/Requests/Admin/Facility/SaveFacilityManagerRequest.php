<?php

namespace App\Http\Requests\Admin\Facility;

use App\Http\Requests\Concerns\NormalisesBranchPhones;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One manager saved on its own from the facility form's manager modal.
 *
 * The facility is taken from the route, never from the payload, so a manager
 * can only ever be written under the facility being edited.
 */
class SaveFacilityManagerRequest extends FormRequest
{
    use NormalisesBranchPhones;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Split combined numbers ("011.../022...") into one per entry and give each
     * one its kind of line, exactly as a branch's phones are handled.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phones')) {
            $this->merge(['phones' => $this->normalisedPhones($this->input('phones'))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|exists:facility_managers,id',
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            ...$this->phoneRules('phones'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'The manager being edited no longer exists.',
            'name.required' => 'Manager name is required.',
            ...$this->phoneMessages('phones'),
        ];
    }
}
