<?php

namespace App\Http\Requests\Admin\Setting;

use App\Http\Requests\Admin\Setting\Concerns\ValidatesSettingValue;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSettingRequest extends FormRequest
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
             * The key stays editable — a typo would otherwise be permanent —
             * but it is what the application reads the row by, so the form
             * warns before letting it change.
             */
            'slug' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                Rule::unique('settings', 'slug')->ignore($this->route('setting')),
            ],
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

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (is_string($slug)) {
            $this->merge(['slug' => strtolower(trim($slug))]);
        }
    }
}
