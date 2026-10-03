<?php

namespace App\Http\Requests\Admin\Tag;

use App\Enums\Tag\TagTargetEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
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
            /*
             * The name arrives as a map of locale to text — see the product
             * type requests, which validate the same shape.
             */
            'name' => ['required', 'array'],
            'name.*' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'applies_to' => ['required', 'array', 'min:1'],
            'applies_to.*' => ['string', Rule::in(TagTargetEnum::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'applies_to.required' => 'Pick at least one thing this tag applies to.',
            'applies_to.min' => 'Pick at least one thing this tag applies to.',
            'name.required' => 'The name is required.',
            'name.array' => 'The name must be given per language.',
            'name.*.required' => 'Each language name is required.',
            'name.*.string' => 'Each language name must be a string.',
            'name.*.max' => 'Each language name may not be greater than 255 characters.',
        ];
    }
}
