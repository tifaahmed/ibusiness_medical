<?php

namespace App\Http\Requests\Admin\Setting;

use App\Http\Requests\Admin\Setting\Concerns\ValidatesSettingValue;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSettingRequest extends FormRequest
{
    use ValidatesSettingValue;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            /*
             * The slug is the key the application reads the row by
             * (SiteSettings::get('deilar_phone')), so it is restricted to the
             * shape a key can safely take and has to be unique.
             */
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                Rule::unique('settings', 'slug'),
            ],
            // The label shown in the admin, per language.
            'name' => ['required', 'array'],
            'name.*' => ['required', 'string', 'max:255'],
            'value_type' => ['required', Rule::in(Setting::VALUE_TYPES)],
        ], $this->valueRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->valueMessages(), [
            'slug.required' => 'The key is required.',
            'slug.unique' => 'A setting with this key already exists.',
            'slug.regex' => 'The key may only use lowercase letters, numbers, dashes and underscores — for example deilar_phone.',
            'name.required' => 'The name is required.',
            'name.*.required' => 'Each language name is required.',
            'value_type.required' => 'Choose what kind of value this setting holds.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->validateValueShape($v));
    }

    /**
     * Keys are typed by hand, so normalise the obvious slips rather than
     * bouncing the form over a capital letter or a stray space.
     */
    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (is_string($slug)) {
            $this->merge(['slug' => strtolower(trim($slug))]);
        }
    }
}
