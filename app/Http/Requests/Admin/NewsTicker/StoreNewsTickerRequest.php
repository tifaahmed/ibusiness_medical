<?php

namespace App\Http\Requests\Admin\NewsTicker;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsTickerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|array',
            'title.ar' => 'required|string|max:500',
            'title.en' => 'nullable|string|max:500',
            'description' => 'required|array',
            'description.ar' => 'required|string',
            'description.en' => 'nullable|string',
            'category' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'mobile_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
