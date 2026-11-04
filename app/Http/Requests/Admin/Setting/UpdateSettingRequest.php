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
        // Decided BEFORE validation, so a refused row answers 403 rather than a validation error. A missing row is left to the controller's 404.
        $row = Setting::find($this->route('setting'));

        return $row === null || \App\Support\SettingAccess::canEdit($this->user(), $row);
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
        // Someone who may only edit the quick-add messages changes the TEXT and nothing else: key, names and type stay as stored.
        $row = Setting::find($this->route('setting'));
        if ($row && \App\Support\SettingAccess::isTemplate($row) && ! $this->user()?->can(\App\Enums\User\UserPermissionEnum::MANAGE_SETTINGS)) {
            $this->merge(['slug' => $row->slug, 'name' => $row->getTranslations('name'), 'value_type' => $row->value_type]);
        }

        $slug = $this->input('slug');

        if (is_string($slug)) {
            $this->merge(['slug' => strtolower(trim($slug))]);
        }
    }
}
