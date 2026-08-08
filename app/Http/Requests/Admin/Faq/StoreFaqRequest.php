<?php

namespace App\Http\Requests\Admin\Faq;

use Illuminate\Foundation\Http\FormRequest;

class StoreFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => 'required|array',
            'question.ar' => 'required|string|max:500',
            'question.en' => 'nullable|string|max:500',
            'answer' => 'required|array',
            'answer.ar' => 'required|string',
            'answer.en' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
