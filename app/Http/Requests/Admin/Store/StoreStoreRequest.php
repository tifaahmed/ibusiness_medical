<?php

namespace App\Http\Requests\Admin\Store;

use App\Http\Requests\Concerns\HandlesStoreExtras;
use App\Http\Requests\Concerns\LogsFailedValidation;
use App\Http\Requests\Concerns\NormalisesBranchPhones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStoreRequest extends FormRequest
{
    use HandlesStoreExtras;
    use LogsFailedValidation;
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
        $this->cleanStoreExtras();
        $this->cleanStoreManagers();

        // An online-only store has no branches: whatever the form still holds
        // is dropped before validation, so a hidden half-filled branch cannot
        // fail the save.
        if ($this->boolean('online_only')) {
            $this->merge(['branches' => []]);
        }

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
        return array_merge($this->storeExtrasRules(), $this->storeContactRules(), [
            'title' => ['required', 'array'],
            'title.*' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:30000'],
            'short_description' => ['nullable', 'array'],
            'short_description.*' => ['nullable', 'string', 'max:500'],
            'youtube_link' => ['nullable', 'url', 'max:255'],
            'online_only' => ['nullable', 'boolean'],
            'editor_gallery_paths' => ['nullable', 'array'],
            'editor_gallery_paths.*' => ['string', 'max:2048'],
            'app_store_url' => ['nullable', 'url', 'max:2048'],
            'google_play_url' => ['nullable', 'url', 'max:2048'],
            'offer_percent_from' => ['nullable', 'numeric', 'between:0,100'],
            'offer_percent_to' => ['nullable', 'numeric', 'between:0,100'],
            'shipping_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'free_shipping_threshold' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'shipping_group_id' => ['nullable', 'integer', 'exists:shipping_groups,id'],
            'new_shipping_group_name' => ['nullable', 'string', 'max:120'],
            'group_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'group_free_threshold' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'shipping_rates' => ['nullable', 'array', 'max:60'],
            'shipping_rates.*.governorate_id' => ['required', 'integer', 'exists:governorates,id', 'distinct'],
            'shipping_rates.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'shipping_rates.*.free_threshold' => ['nullable', 'numeric', 'min:0', 'max:10000000'],

            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif'],
            'seo_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif'],
            'header' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif'],

            // Gallery images and uploaded videos both cap at 5MB per file.
            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpeg,jpg,png,gif,webp,avif'],
            'gallery_videos' => ['nullable', 'array'],
            'gallery_videos.*' => ['mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo', 'mimes:mp4,mov,webm,avi'],

            'branches' => ['nullable', 'array'],
            'branches.*.governorate_id' => ['required', 'exists:governorates,id'],
            'branches.*.city_id' => ['required', 'exists:cities,id'],
            'branches.*.name' => ['nullable', 'array'],
            'branches.*.name.*' => ['nullable', 'string', 'max:255'],
            'branches.*.address' => ['nullable', 'array'],
            'branches.*.address.*' => ['nullable', 'string', 'max:500'],
            'branches.*.area' => ['nullable', 'array'],
            'branches.*.area.*' => ['nullable', 'string', 'max:255'],
            'branches.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'branches.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'branches.*.google_location_url' => ['nullable', 'url', 'max:2048'],
        ], $this->phoneRules('branches.*.phone'));
    }

    public function messages(): array
    {
        return array_merge($this->storeExtrasMessages(), $this->storeContactMessages(), [
            'title.required' => 'The title field is required.',
            'title.*.required' => 'Each language title is required.',
            'branches.*.governorate_id.required' => 'Choose the branch governorate.',
            'branches.*.city_id.required' => 'Choose the branch city.',
            'branches.*.latitude.required' => 'Set the branch location on the map (latitude is required).',
            'branches.*.longitude.required' => 'Set the branch location on the map (longitude is required).',
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
