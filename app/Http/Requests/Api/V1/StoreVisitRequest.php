<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Visitor\VisitSourceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One page view from the website (key-gated partner route) or an app (public
 * mobile route). On the partner route the visitor's own `ip_address` and
 * `user_agent` come in the body, since `$request->ip()` there is the website's
 * server; on the mobile route the request itself is the visitor.
 */
class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function isMobile(): bool
    {
        return $this->routeIs('*mobile.*');
    }

    public function rules(): array
    {
        return [
            'visitor_key' => ['required', 'string', 'min:8', 'max:80', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'platform' => [$this->isMobile() ? 'required' : 'nullable', Rule::in(['android', 'ios'])],
            'page' => ['required', 'string', 'max:100'],
            'url' => ['nullable', 'string', 'max:2000'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'max:5'],
            'visited_at' => ['nullable', 'date'],
            'subject_type' => ['nullable', Rule::in(['product', 'facility'])],
            'subject_slug' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'governorate_name' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'os' => ['nullable', 'string', 'max:60'],
            'app_version' => ['nullable', 'string', 'max:30'],
            'ip_address' => ['nullable', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, mixed> */
    public function visit(): array
    {
        $data = $this->validated();

        if ($this->isMobile()) {
            $data['source'] = $data['platform'];
            $data['ip_address'] = $this->ip();
            $data['user_agent'] = $data['user_agent'] ?? $this->userAgent();
        } else {
            $data['source'] = VisitSourceEnum::WEB->value;
            $data['ip_address'] = $data['ip_address'] ?? null;
        }
        unset($data['platform']);

        return $data;
    }
}
