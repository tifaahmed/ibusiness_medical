<?php

namespace App\Http\Requests\Admin\CardTemplate;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Support\CardTemplateLayoutDefaults;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardTemplateRequest extends FormRequest
{
    /**
     * Sample image fields uploaded as files under a `sample_{field}` key.
     * These land in the sample_data JSON column, so the controller must strip
     * them from the validated payload before create/update — they are not
     * columns of their own.
     */
    public const UPLOADABLE_SAMPLE_IMAGE_FIELDS = CardTemplateLayoutDefaults::IMAGE_FIELDS;

    /** The `sample_{field}` request keys for the fields above. */
    public static function sampleImageUploadKeys(): array
    {
        return array_map(fn (string $field) => "sample_{$field}", self::UPLOADABLE_SAMPLE_IMAGE_FIELDS);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templateId = $this->route('cardTemplate')?->id;

        $rules = [
            'name' => ['required', 'array'],
            'name.ar' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('card_templates', 'slug')->ignore($templateId)],
            'status' => ['nullable', Rule::in(CardTemplateStatusEnum::values())],
            'card_empty' => ['nullable', 'image', 'max:5120'],
            'sample_card' => ['nullable', 'image', 'max:5120'],
            'clear_sample_card' => ['nullable', 'boolean'],
            'layout' => ['nullable', 'array'],
            'sample_data' => ['nullable', 'array'],
            'sample_data.qrcode' => ['nullable', 'string', 'max:2048'],
            'sample_data.qrcode_color' => ['nullable', 'string', 'max:20'],
        ];

        foreach (CardTemplateLayoutDefaults::fields() as $field) {
            // x/y allow a range beyond [0,1] so a field can be dragged outside
            // the card frame in the editor without failing validation.
            $rules["layout.{$field}.x"] = ['required_with:layout', 'numeric', 'min:-1', 'max:2'];
            $rules["layout.{$field}.y"] = ['required_with:layout', 'numeric', 'min:-1', 'max:2'];
            $rules["layout.{$field}.width"] = ['required_with:layout', 'numeric', 'min:0', 'max:2'];
            $rules["layout.{$field}.height"] = ['required_with:layout', 'numeric', 'min:0', 'max:2'];
        }

        foreach (CardTemplateLayoutDefaults::TEXT_FIELDS as $field) {
            $rules["layout.{$field}.font_family"] = ['nullable', 'string', 'max:64'];
            $rules["layout.{$field}.direction"] = ['nullable', Rule::in(['ltr', 'rtl', 'center'])];
            $rules["layout.{$field}.color"] = ['nullable', 'string', 'max:20'];
            $rules["layout.{$field}.font_size"] = ['nullable', 'numeric', 'min:1', 'max:200'];

            $rules["sample_data.{$field}"] = ['nullable', 'string', 'max:255'];
        }

        foreach (self::UPLOADABLE_SAMPLE_IMAGE_FIELDS as $field) {
            $rules["sample_{$field}"] = ['nullable', 'image', 'max:5120'];
        }

        return $rules;
    }

    /**
     * The admin form posts as multipart/form-data so it can carry the artwork
     * upload, which means the JSON columns arrive as strings — decode them
     * before the array rules above run.
     */
    protected function prepareForValidation(): void
    {
        foreach (['name', 'layout', 'sample_data'] as $key) {
            if (! is_string($this->input($key))) {
                continue;
            }

            $decoded = json_decode($this->input($key), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge([$key => $decoded]);
            }
        }
    }
}
