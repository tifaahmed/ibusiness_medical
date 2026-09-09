<?php

namespace App\Http\Requests\Admin\Facility;

use App\Http\Requests\Concerns\NormalisesBranchPhones;
use App\Models\Facility;
use App\Support\BranchUniqueness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One branch saved on its own from the facility form's branch modal.
 *
 * The facility is taken from the route, never from the payload, so a branch
 * can only ever be written under the facility being edited.
 */
class SaveFacilityBranchRequest extends FormRequest
{
    use NormalisesBranchPhones;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the phone list: split combined strings ("011.../022...") into
     * one number per entry and give every entry a type, exactly as the full
     * facility save does.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => $this->normalisedPhones($this->input('phone'))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|exists:facility_branches,id',
            'governorate_id' => 'required|exists:governorates,id',
            'city_id' => 'required|exists:cities,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_location_url' => 'nullable|url|max:2048',
            'name' => 'required|array',
            'name.*' => 'nullable|string|max:255',
            // Required in BOTH languages: a branch listed in one language only
            // shows up blank on the other side of the directory, and the
            // address is what the AI geocoder reads to place the pin.
            'address' => 'required|array',
            'address.ar' => 'required|string|max:500',
            'address.en' => 'required|string|max:500',
            ...$this->phoneRules(),
        ];
    }

    /**
     * The branch needs a name in at least one language, and may not repeat the
     * name or address of another branch under the same facility.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $names = (array) $this->input('name', []);
            $filled = array_filter($names, fn ($value) => is_string($value) && trim($value) !== '');

            if ($filled === []) {
                $v->errors()->add('name', 'Branch name is required in at least one language.');

                return;
            }

            $facilityId = Facility::where('slug', $this->route('facility'))->value('id');

            $duplicates = BranchUniqueness::duplicatesInFacility(
                $this->only(BranchUniqueness::FIELDS),
                $facilityId,
                $this->input('id') ? (int) $this->input('id') : null,
            );

            foreach ($duplicates as $error) {
                $v->errors()->add($error['key'], $error['message']);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'The branch being edited no longer exists.',
            'governorate_id.required' => 'Choose the governorate this branch is in.',
            'city_id.required' => 'Choose the city this branch is in.',
            'address.required' => 'The branch address is required in both Arabic and English.',
            'address.ar.required' => 'The Arabic branch address is required.',
            'address.en.required' => 'The English branch address is required.',
            'google_location_url.url' => 'The Google location URL must be a full URL, e.g. https://maps.app.goo.gl/xxxx.',
            'phone.*.max' => 'Each branch phone number must be 20 characters or fewer — put one number per line.',
        ];
    }
}
