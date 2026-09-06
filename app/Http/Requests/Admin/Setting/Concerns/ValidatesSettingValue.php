<?php

namespace App\Http\Requests\Admin\Setting\Concerns;

use App\Models\Setting;
use Illuminate\Validation\Validator;

/**
 * The value rules both the store and update requests apply.
 *
 * A setting's column is plain text, so what counts as a valid value depends
 * entirely on the `value_type` the admin picked. Checking it here means a URL
 * setting cannot be saved holding a phone number, and a JSON setting cannot be
 * saved holding something no reader can decode.
 */
trait ValidatesSettingValue
{
    /**
     * The rules for the scalar `value` field, plus the file for an image row.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function valueRules(): array
    {
        return [
            // Every type stores text; the shape check happens in after().
            'value' => ['nullable', 'string', 'max:65535'],
            // An `image` row stores the path of this upload, never a URL. The
            // mime list is deliberately narrower than the `image` rule, which
            // would also let an SVG through — and an SVG is a document that can
            // carry script, not a picture.
            'value_image' => [
                'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp,avif',
                'max:'.Setting::maxUploadKilobytes(),
            ],
            // Edit only: clear the stored file without putting a new one up.
            'value_image_delete' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The messages for those rules, shared by both requests.
     *
     * @return array<string, string>
     */
    protected function valueMessages(): array
    {
        $megabytes = round(Setting::maxUploadKilobytes() / 1024, 1);

        return [
            'value_image.image' => 'That file is not an image.',
            'value_image.mimes' => 'Upload a JPG, PNG, GIF, WebP or AVIF image.',
            'value_image.max' => "The image may be at most {$megabytes} MB.",
        ];
    }

    /**
     * Check the value against the type the admin chose.
     */
    protected function validateValueShape(Validator $validator): void
    {
        $type = $this->input('value_type');
        $value = $this->input('value');

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $value = trim($value);

        $failure = match ($type) {
            Setting::TYPE_URL => filter_var($value, FILTER_VALIDATE_URL) === false
                ? 'Enter a full address, starting with http:// or https://.'
                : null,
            Setting::TYPE_EMAIL => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'Enter a valid email address.'
                : null,
            Setting::TYPE_NUMBER => ! is_numeric($value)
                ? 'Enter a number.'
                : null,
            // json_decode returns null both for invalid JSON and for the valid
            // document "null", so the literal has to be let through by hand.
            Setting::TYPE_JSON => json_decode($value, true) === null && strtolower($value) !== 'null'
                ? 'Enter valid JSON.'
                : null,
            default => null,
        };

        if ($failure !== null) {
            $validator->errors()->add('value', $failure);
        }
    }
}
