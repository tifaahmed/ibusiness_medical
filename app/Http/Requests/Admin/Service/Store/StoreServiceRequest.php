<?php

namespace App\Http\Requests\Admin\Service\Store;

use App\Enums\Service\ServiceTagEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:service_types,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:services'],
            'short_subject' => ['nullable', 'string', 'max:500'],
            'subject' => ['nullable', 'string'],
            'old_price' => ['nullable', 'numeric', 'min:0'],
            'new_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,avif', 'max:2048'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp,avif', 'max:2048'],
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

    public function messages(): array
    {
        return [
            'category_id.required' => 'The service type is required.',
            'title.required' => 'The title is required.',
            'category_id.exists' => 'The selected service type is invalid.',
        ];
    }
}
