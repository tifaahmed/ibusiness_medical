<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProductSeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The form sends whatever the admin has typed so far — nothing is persisted
     * here, it is only forwarded to the model as context.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
            'short_subject' => ['nullable', 'array'],
            'short_subject.ar' => ['nullable', 'string', 'max:255'],
            'short_subject.en' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.ar' => ['nullable', 'string', 'max:65535'],
            'description.en' => ['nullable', 'string', 'max:65535'],
            'product_type' => ['nullable', 'string', 'max:255'],
            'old_price' => ['nullable', 'numeric', 'min:0'],
            'new_price' => ['nullable', 'numeric', 'min:0'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter the product name first — the AI needs it to write the metadata.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // A product with no name at all gives the model nothing to work with.
        $name = (array) $this->input('name', []);

        $this->merge([
            'name' => array_filter($name, fn ($value) => is_string($value)),
        ]);
    }
}
