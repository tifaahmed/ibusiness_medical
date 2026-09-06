<?php

namespace App\Http\Requests\Admin\FacilityBranch;

use App\Http\Requests\Concerns\NormalisesBranchPhones;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\Governorate;
use App\Support\BranchUniqueness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFacilityBranchRequest extends FormRequest
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
            'governorate_id' => ['nullable', 'exists:'.Governorate::class.',id'],
            'city_id' => ['nullable', 'exists:'.City::class.',id'],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_location_url' => 'nullable|url|max:2048',
            'name' => 'nullable|array',
            'name.*' => 'nullable|string|max:255',
            'address' => 'nullable|array',
            'address.*' => 'nullable|string',
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

            $ignore = FacilityBranch::where('slug', $this->route('facilityBranch'))->value('id');

            $duplicates = BranchUniqueness::duplicatesInFacility(
                $this->only(BranchUniqueness::FIELDS),
                $this->input('facility_id'),
                $ignore,
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
            'governorate_id.exists' => 'The selected governorate is invalid.',
            'city_id.exists' => 'The selected city is invalid.',
            'latitude.numeric' => 'The latitude must be a number.',
            'latitude.between' => 'The latitude must be between -90 and 90.',
            'longitude.numeric' => 'The longitude must be a number.',
            'longitude.between' => 'The longitude must be between -180 and 180.',
            'google_location_url.url' => 'The Google location URL must be a valid URL.',
            'google_location_url.max' => 'The Google location URL may not be greater than 2048 characters.',
            'name.array' => 'The name must be an array.',
            'name.*.string' => 'Each language name must be a string.',
            'address.array' => 'The address must be an array.',
            ...$this->phoneMessages(),
        ];
    }
}
