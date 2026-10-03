<?php

namespace App\Http\Requests\Admin\Tag;

use App\Enums\Tag\TagTargetEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
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
            'name' => ['sometimes', 'array'],
            'name.*' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
            'applies_to' => ['sometimes', 'required', 'array', 'min:1'],
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
            'name.array' => 'The name must be given per language.',
            'name.*.required' => 'Each language name is required.',
            'name.*.string' => 'Each language name must be a string.',
            'name.*.max' => 'Each language name may not be greater than 255 characters.',
        ];
    }
}
