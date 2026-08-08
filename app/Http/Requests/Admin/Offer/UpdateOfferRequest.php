<?php

namespace App\Http\Requests\Admin\Offer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfferRequest extends FormRequest
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
            'offerable_type' => [
                'required',
                'string',
                Rule::in(['App\\Models\\Facility', 'App\\Models\\FacilityBranch']),
            ],
            'offerable_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $type = $this->input('offerable_type');
                    if ($type && !class_exists($type)) {
                        $fail('The selected offerable type is invalid.');
                        return;
                    }
                    if ($type && !$type::where('id', $value)->exists()) {
                        $fail('The selected offerable is invalid.');
                    }
                },
            ],
            'title' => 'nullable|array',
            'title.*' => 'nullable|string|max:255',
            'short_description' => 'nullable|array',
            'short_description.*' => 'nullable|string',
            'full_description' => 'nullable|array',
            'full_description.*' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'image'            => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'mobile_image'     => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'thumbnail'        => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
            'mobile_thumbnail' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
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
            'offerable_type.required' => 'The offerable type is required.',
            'offerable_type.in' => 'The selected offerable type is invalid.',
            'offerable_id.required' => 'The offerable is required.',
            'offerable_id.integer' => 'The offerable ID must be an integer.',
            'title.array' => 'The title must be an array.',
            'title.*.string' => 'Each language title must be a string.',
            'title.*.max' => 'Each title may not be greater than 255 characters.',
            'short_description.array' => 'The short description must be an array.',
            'short_description.*.string' => 'Each language short description must be a string.',
            'full_description.array' => 'The full description must be an array.',
            'full_description.*.string' => 'Each language full description must be a string.',
            'phone.string' => 'The phone must be a string.',
            'phone.max' => 'The phone may not be greater than 50 characters.',
            'price.numeric' => 'The price must be a number.',
            'price.min' => 'The price must be at least 0.',
            'old_price.numeric' => 'The old price must be a number.',
            'old_price.min' => 'The old price must be at least 0.',
            'image.image' => 'The image must be an image file.',
            'image.mimes' => 'The image must be a file of type: jpeg, jpg, png, gif, webp, avif.',
            'image.max' => 'The image may not be greater than 5MB.',
            'thumbnail.image' => 'The thumbnail must be an image file.',
            'thumbnail.mimes' => 'The thumbnail must be a file of type: jpeg, jpg, png, gif, webp, avif.',
            'thumbnail.max' => 'The thumbnail may not be greater than 2MB.',
        ];
    }
}
