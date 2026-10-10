<?php

namespace App\Http\Requests\Admin\Store;

use App\Http\Requests\Concerns\HandlesStoreExtras;
use App\Http\Requests\Concerns\LogsFailedValidation;
use App\Http\Requests\Concerns\NormalisesBranchPhones;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStoreRequest extends FormRequest
{
    use HandlesStoreExtras;
    use LogsFailedValidation;
    use NormalisesBranchPhones;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->cleanStoreExtras();

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
        return array_merge($this->storeExtrasRules(), [
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

            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif', 'max:5120'],
            'logo_delete' => ['nullable', 'boolean'],
            'seo_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif', 'max:5120'],
            'seo_image_delete' => ['nullable', 'boolean'],
            'header' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif', 'max:5120'],
            'header_delete' => ['nullable', 'boolean'],

            'gallery_images' => ['nullable', 'array'],
            'gallery_images.*' => ['image', 'mimes:jpeg,jpg,png,gif,webp,avif', 'max:5120'],
            'gallery_videos' => ['nullable', 'array'],
            'gallery_videos.*' => ['mimetypes:video/mp4,video/quicktime,video/webm,video/x-msvideo', 'mimes:mp4,mov,webm,avi', 'max:5120'],
            'gallery_delete' => ['nullable', 'array'],
            'gallery_delete.*' => ['integer'],

            'branches' => ['nullable', 'array'],
            'branches.*.governorate_id' => ['nullable', 'exists:governorates,id'],
            'branches.*.city_id' => ['nullable', 'exists:cities,id'],
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
        return array_merge($this->storeExtrasMessages(), [
            'title.required' => 'The title field is required.',
            'title.*.required' => 'Each language title is required.',
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
