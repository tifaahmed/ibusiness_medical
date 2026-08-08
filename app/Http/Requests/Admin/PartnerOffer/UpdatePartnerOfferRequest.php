<?php

namespace App\Http\Requests\Admin\PartnerOffer;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id' => 'required|exists:partners,id',
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'old_price' => 'nullable|numeric|min:0',
            'new_price' => 'nullable|numeric|min:0',
            'phone_number' => 'nullable|string|max:20',
            'operator' => 'nullable|string|in:vodafone,etisalat,orange,we',
            'header_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'mobile_header_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'small_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'mobile_small_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'deleted_gallery_ids' => 'nullable|array',
            'deleted_gallery_ids.*' => 'integer|exists:media,id',
        ];
    }
}
