<?php

namespace App\Http\Requests\Api\V1\Partner;

use App\Enums\Contact\ContactSourceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An enquiry forwarded from a partner storefront's public form.
 *
 * The visitor fields are the point of this endpoint being key-gated: the
 * caller is a server speaking for somebody else, so `ip_address`, `user_agent`,
 * `locale` and `referrer` describe the VISITOR and arrive in the body —
 * `$request->ip()` here is the storefront.
 */
class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /* The partner key is the authorisation, checked by middleware before
           this request is ever built. */
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:5000'],
            'name' => ['nullable', 'string', 'max:255'],
            'commercial_register' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', Rule::in(ContactSourceEnum::values())],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string', 'max:1000'],
            'locale' => ['nullable', 'string', 'max:5'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            /*
             * Backfill only — the storefront's one-off command copying
             * enquiries that were written before they lived here. A form never
             * sends it, and it cannot be in the future.
             */
            'created_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * The enquiry as the recording action wants it.
     *
     * NOT called `attributes()`: that name belongs to Laravel, which uses it
     * for the human names of fields in validation messages.
     *
     * @return array<string, mixed>
     */
    public function enquiry(): array
    {
        return array_filter([
            'phone' => $this->string('phone')->toString(),
            'message' => $this->string('message')->toString(),
            'name' => $this->input('name'),
            'commercial_register' => $this->input('commercial_register'),
            'source' => $this->input('source', ContactSourceEnum::CONTACT_FORM->value),
            'ip_address' => $this->input('ip_address'),
            'user_agent' => $this->input('user_agent'),
            'locale' => $this->input('locale'),
            'referrer' => $this->input('referrer'),
            'created_at' => $this->input('created_at'),
        ], fn ($value) => $value !== null);
    }
}
