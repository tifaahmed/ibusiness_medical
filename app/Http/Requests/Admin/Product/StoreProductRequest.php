<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|array',
            'name.*' => 'required|string|max:255',
            'short_subject' => 'nullable|array',
            'short_subject.*' => 'nullable|string|max:255',
            'old_price' => 'nullable|numeric|min:0',
            'new_price' => 'nullable|numeric|min:0',
            'product_type_id' => 'nullable|exists:product_types,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'large_image' => 'nullable|image|max:10240',
            'small_image' => 'nullable|image|max:10240',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.array' => 'The name must be an array.',
            'name.*.required' => 'Each language name is required.',
            'name.*.string' => 'Each language name must be a string.',
            'name.*.max' => 'Each language name may not be greater than 255 characters.',
            'tag_ids.array' => 'The tags must be an array.',
            'tag_ids.*.exists' => 'One or more selected tags are invalid.',
            'large_image.image' => 'The large image must be an image file.',
            'small_image.image' => 'The small image must be an image file.',
            'gallery.*.image' => 'Each gallery item must be an image file.',
        ];
    }
}
