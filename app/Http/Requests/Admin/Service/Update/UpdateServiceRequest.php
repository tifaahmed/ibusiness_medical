<?php

namespace App\Http\Requests\Admin\Service\Update;

use App\Enums\Service\ServiceTagEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:service_types,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'short_subject' => ['nullable', 'string', 'max:500'],
            'subject' => ['nullable', 'string'],
            'old_price' => ['nullable', 'numeric', 'min:0'],
            'new_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,avif', 'max:2048'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp,avif', 'max:2048'],
            'deleted_gallery_ids' => ['nullable', 'array'],
            'deleted_gallery_ids.*' => ['integer', 'exists:media,id'],
            'governorate_id' => ['nullable', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'iframe_location' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'long' => ['nullable', 'numeric', 'between:-180,180'],
            'tag' => ['nullable', 'string', Rule::in(ServiceTagEnum::values())],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }
}
