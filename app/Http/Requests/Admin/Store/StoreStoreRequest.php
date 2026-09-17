<?php

namespace App\Http\Requests\Admin\Store;

use App\Http\Requests\Concerns\NormalisesBranchPhones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStoreRequest extends FormRequest
{
    use NormalisesBranchPhones;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise each branch's phone list before the rules below see it —
     * same reasoning as the facility form: a flat list of strings is typed
     * by {@see \App\Support\PhoneNumbers::guessType()} rather than rejected.
     */
    protected function prepareForValidation(): void
    {
        $branches = $this->input('branches', []);
        if (! is_array($branches)) {
            return;
        }

        foreach ($branches as $index => $branch) {
            $branches[$index]['phone'] = $this->normalisedPhones($branch['phone'] ?? null);
        }

        $this->merge(['branches' => $branches]);
    }

    public function rules(): array
    {
        return array_merge([
            'title' => ['required', 'array'],
            'title.*' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:5000'],
            'short_description' => ['nullable', 'array'],
            'short_description.*' => ['nullable', 'string', 'max:500'],
            'youtube_link' => ['nullable', 'url', 'max:255'],
            'offer_percent_from' => ['nullable', 'numeric', 'between:0,100'],
            'offer_percent_to' => ['nullable', 'numeric', 'between:0,100'],

            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif', 'max:5120'],
            'header' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif', 'max:5120'],

            // Gallery images and uploaded videos both cap at 5MB per file.
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpeg,jpg,png,gif,webp,avif', 'max:5120'],
            'gallery_videos' => ['nullable', 'array'],
            'gallery_videos.*' => ['mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo', 'mimes:mp4,mov,webm,avi', 'max:5120'],

            'branches' => ['nullable', 'array'],
            'branches.*.governorate_id' => ['required', 'exists:governorates,id'],
            'branches.*.city_id' => ['required', 'exists:cities,id'],
            'branches.*.name' => ['nullable', 'array'],
            'branches.*.name.*' => ['nullable', 'string', 'max:255'],
            'branches.*.address' => ['nullable', 'array'],
            'branches.*.address.*' => ['nullable', 'string', 'max:500'],
            'branches.*.area' => ['nullable', 'array'],
            'branches.*.area.*' => ['nullable', 'string', 'max:255'],
            'branches.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'branches.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'branches.*.google_location_url' => ['nullable', 'url', 'max:2048'],
        ], $this->phoneRules('branches.*.phone'));
    }

    public function messages(): array
    {
        return array_merge([
            'title.required' => 'The title field is required.',
            'title.*.required' => 'Each language title is required.',
            'branches.*.governorate_id.required' => 'Choose the branch governorate.',
            'branches.*.city_id.required' => 'Choose the branch city.',
        ], $this->phoneMessages('branches.*.phone'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $from = $this->input('offer_percent_from');
            $to = $this->input('offer_percent_to');

            if ($from !== null && $to !== null && (float) $to < (float) $from) {
                $validator->errors()->add('offer_percent_to', 'The offer\'s upper percentage must be greater than or equal to its lower one.');
            }
        });
    }
}
