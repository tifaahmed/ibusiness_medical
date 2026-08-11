<?php

namespace App\Http\Requests\Admin\Facility;

use App\Models\FacilityType;
use App\Models\Sales;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFacilityRequest extends FormRequest
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
            'name' => 'required|array',
            'name.*' => 'required|string|max:255',
            'description' => 'nullable|array',
            'description.*' => 'nullable|string|max:5000',
            // SEO tab. Lengths match what search engines actually render.
            'meta_title' => 'nullable|array',
            'meta_title.*' => 'nullable|string|max:60',
            'meta_description' => 'nullable|array',
            'meta_description.*' => 'nullable|string|max:160',
            'meta_keywords' => 'nullable|array',
            'meta_keywords.*' => 'nullable|string|max:255',
            'canonical_url' => 'nullable|url|max:2048',
            'og_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'og_image_delete' => 'nullable|boolean',
            'facility_type_id' => ['required', 'exists:'.FacilityType::class.',id'],
            'sales_id' => ['nullable', 'exists:'.Sales::class.',id'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'branches' => 'nullable|array',
            'branches.*.id' => 'nullable|exists:facility_branches,id',
            'branches.*.governorate_id' => 'nullable|exists:governorates,id',
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'branches.*.city_id' => 'nullable|exists:cities,id',
            'branches.*.latitude' => 'nullable|numeric|between:-90,90',
            'branches.*.longitude' => 'nullable|numeric|between:-180,180',
            'branches.*.name' => 'nullable|array',
            'branches.*.name.*' => 'nullable|string|max:255',
            'branches.*.address' => 'nullable|array',
            'branches.*.address.*' => 'nullable|string|max:500',
            'branches.*.phone' => 'nullable|array',
            'branches.*.phone.*' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'mobile_logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'mobile_image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'gallery' => 'nullable|array',
            'gallery.*' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'gallery_delete' => 'nullable|array',
            'gallery_delete.*' => 'nullable|integer',
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
            'name.required' => 'The name field is required.',
            'name.array' => 'The name must be an array.',
            'name.*.required' => 'Each language name is required.',
            'name.*.string' => 'Each language name must be a string.',
            'facility_type_id.required' => 'The facility type is required.',
            'facility_type_id.exists' => 'The selected facility type is invalid.',
            'meta_title.*.max' => 'The meta title should stay under 60 characters.',
            'meta_description.*.max' => 'The meta description should stay under 160 characters.',
            'canonical_url.url' => 'The canonical URL must be a full URL, e.g. https://example.com/facility.',
            'sales_id.exists' => 'The selected sales representative is invalid.',
        ];
    }
}
