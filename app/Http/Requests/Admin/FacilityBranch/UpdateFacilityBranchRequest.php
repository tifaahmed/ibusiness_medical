<?php

namespace App\Http\Requests\Admin\FacilityBranch;

use App\Models\City;
use App\Models\Facility;
use App\Models\Governorate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFacilityBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'exists:' . Facility::class . ',id'],
            'governorate_id' => ['nullable', 'exists:' . Governorate::class . ',id'],
            'city_id' => ['nullable', 'exists:' . City::class . ',id'],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'name' => 'nullable|array',
            'name.*' => 'nullable|string|max:255',
            'address' => 'nullable|array',
            'address.*' => 'nullable|string',
            'phone' => 'nullable|array',
            'phone.*' => 'nullable|string|max:50',
        ];
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
            'name.array' => 'The name must be an array.',
            'name.*.string' => 'Each language name must be a string.',
            'address.array' => 'The address must be an array.',
            'phone.array' => 'The phone must be an array.',
            'phone.*.string' => 'Each phone number must be a string.',
            'phone.*.max' => 'Each phone number may not be greater than 50 characters.',
        ];
    }
}


