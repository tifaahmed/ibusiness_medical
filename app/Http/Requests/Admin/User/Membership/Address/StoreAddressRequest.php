<?php

namespace App\Http\Requests\Admin\User\Membership\Address;

use App\Enums\Address\AddressTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddressRequest extends FormRequest
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
            'type' => ['required', Rule::enum(AddressTypeEnum::class)],
            'address' => 'nullable|string|max:1000',
            'street' => 'nullable|string|max:255',
            'governorate_id' => 'nullable|integer|exists:governorates,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'building_number' => 'nullable|string|max:50',
            'apartment_number' => 'nullable|string|max:50',
            'floor_number' => 'nullable|string|max:50',
            'special_mark' => 'nullable|string|max:500',
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
            'type.required' => 'The address type field is required.',
            'type.enum' => 'The selected address type is invalid.',
            'address.max' => 'The address may not be greater than 1000 characters.',
            'governorate_id.exists' => 'The selected governorate is invalid.',
            'city_id.exists' => 'The selected city is invalid.',
            'special_mark.max' => 'The special mark may not be greater than 500 characters.',
        ];
    }
}
