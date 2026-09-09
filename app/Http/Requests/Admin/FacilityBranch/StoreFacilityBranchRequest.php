<?php

namespace App\Http\Requests\Admin\FacilityBranch;

use App\Http\Requests\Concerns\NormalisesBranchPhones;
use App\Models\City;
use App\Models\Facility;
use App\Models\Governorate;
use App\Support\BranchUniqueness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFacilityBranchRequest extends FormRequest
{
    use NormalisesBranchPhones;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the phone list before the rules see it: one number per entry,
     * each with the kind of line it is.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => $this->normalisedPhones($this->input('phone'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'exists:'.Facility::class.',id'],
            // Required, as they are in the facility form's branch modal: a
            // branch with no place on the map is what makes the directory
            // unusable, so it is asked for rather than left to be filled later.
            'governorate_id' => ['required', 'exists:'.Governorate::class.',id'],
            'city_id' => ['required', 'exists:'.City::class.',id'],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_location_url' => 'nullable|url|max:2048',
            'name' => 'required|array',
            'name.*' => 'nullable|string|max:255',
            // Required in BOTH languages: a branch listed in one language only
            // shows up blank on the other side of the directory, and the
            // address is what the AI geocoder reads to place the pin.
            'address' => 'required|array',
            'address.ar' => 'required|string',
            'address.en' => 'required|string',
            ...$this->phoneRules(),
        ];
    }

    /**
     * A branch may not repeat the name or the address of another branch under the
     * same facility. Only the branch being saved is in the payload here, so the
     * facility's other branches are read back from the database.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->has('facility_id')) {
                return;
            }

            $names = (array) $this->input('name', []);
            $filled = array_filter($names, fn ($value) => is_string($value) && trim($value) !== '');

            if ($filled === []) {
                $v->errors()->add('name', 'Branch name is required in at least one language.');
            }

            $duplicates = BranchUniqueness::duplicatesInFacility(
                $this->only(BranchUniqueness::FIELDS),
                $this->input('facility_id'),
                null,
            );

            foreach ($duplicates as $error) {
                $v->errors()->add($error['key'], $error['message']);
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'facility_id.required' => 'The facility is required.',
            'facility_id.exists' => 'The selected facility is invalid.',
            'governorate_id.required' => 'Choose the governorate this branch is in.',
            'governorate_id.exists' => 'The selected governorate is invalid.',
            'city_id.required' => 'Choose the city this branch is in.',
            'city_id.exists' => 'The selected city is invalid.',
            'latitude.numeric' => 'The latitude must be a number.',
            'latitude.between' => 'The latitude must be between -90 and 90.',
            'longitude.numeric' => 'The longitude must be a number.',
            'longitude.between' => 'The longitude must be between -180 and 180.',
            'google_location_url.url' => 'The Google location URL must be a valid URL.',
            'google_location_url.max' => 'The Google location URL may not be greater than 2048 characters.',
            'name.array' => 'The name must be an array.',
            'name.required' => 'The branch name is required.',
            'name.*.string' => 'Each language name must be a string.',
            'address.array' => 'The address must be an array.',
            'address.required' => 'The branch address is required in both Arabic and English.',
            'address.ar.required' => 'The Arabic branch address is required.',
            'address.en.required' => 'The English branch address is required.',
            ...$this->phoneMessages(),
        ];
    }
}
