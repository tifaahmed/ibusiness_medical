<?php

namespace App\Http\Requests\Admin\Governorate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The governorate form's "Fix English with AI" button on the create page.
 *
 * The edit page's button works on the saved row; this one carries the name as
 * typed into the open form, because on create there is nothing on disk to
 * read. Nothing is persisted: the English value is handed back into the form.
 */
class TranslateGovernorateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'array'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'name.en' => ['nullable', 'string', 'max:255'],
        ];
    }
}
