<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'description' => 'nullable|array',
            'description.ar' => 'nullable|string|max:65535',
            'description.en' => 'nullable|string|max:65535',
            'old_price' => 'nullable|numeric|min:0',
            'new_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'profit_price' => 'nullable|numeric|min:0',
            'product_type_id' => 'nullable|exists:product_types,id',
            'admin_note' => 'nullable|string|max:5000',
            'meta_title' => 'nullable|array',
            'meta_title.*' => 'nullable|string|max:60',
            'meta_description' => 'nullable|array',
            'meta_description.*' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|array',
            'meta_keywords.*' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|url|max:2048',
            'banner_config' => ['nullable', 'array'],
            'banner_config.enabled' => ['nullable', 'boolean'],
            'banner_config.message_ar' => ['nullable', 'string', 'max:255'],
            'banner_config.message_en' => ['nullable', 'string', 'max:255'],
            'banner_config.text_color' => ['nullable', 'string', 'max:9'],
            'banner_config.bg_color' => ['nullable', 'string', 'max:9'],
            'banner_config.font_size' => ['nullable', 'numeric', 'between:8,72'],
            'banner_config.angle' => ['nullable', 'numeric', 'between:0,360'],
            'banner_config.shadow_color' => ['nullable', 'string', 'max:9'],
            'banner_config.days' => ['nullable', 'numeric', 'between:1,365'],
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
            'sync_tags' => 'nullable|boolean',
            'large_image' => 'nullable|image|max:10240',
            'small_image' => 'nullable|image|max:10240',
            'remove_large_image' => 'nullable|boolean',
            'remove_small_image' => 'nullable|boolean',
            // New gallery files are appended; existing ones are dropped by id.
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|max:10240',
            'removed_gallery_ids' => 'nullable|array',
            'removed_gallery_ids.*' => 'integer',
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
