<?php

namespace App\Http\Requests\Concerns;

/**
 * The store's optional websites, social links and coupons. Blank rows (the form
 * keeps an empty one on screen) are dropped before validation, so an untouched
 * row is "nothing" rather than an error.
 */
trait HandlesStoreExtras
{
    public const SOCIAL_PLATFORMS = ['facebook', 'instagram', 'x', 'tiktok', 'youtube', 'linkedin', 'whatsapp', 'snapchat', 'telegram', 'other'];

    protected function cleanStoreExtras(): void
    {
        $filled = fn ($v) => is_string($v) && trim($v) !== '';

        $websites = collect((array) $this->input('websites', []))
            ->map(fn ($u) => is_string($u) ? trim($u) : '')->filter()->values()->all();

        $social = collect((array) $this->input('social_links', []))
            ->filter(fn ($r) => is_array($r) && $filled($r['url'] ?? null))
            ->map(fn ($r) => ['platform' => $r['platform'] ?? 'other', 'url' => trim($r['url'])])
            ->values()->all();

        $coupons = collect((array) $this->input('coupons', []))
            ->filter(fn ($r) => is_array($r) && ($filled($r['code'] ?? null)
                || $filled(data_get($r, 'title.ar')) || $filled(data_get($r, 'title.en'))))
            ->map(fn ($r) => [
                'code' => trim((string) ($r['code'] ?? '')),
                'title' => ['ar' => trim((string) data_get($r, 'title.ar', '')), 'en' => trim((string) data_get($r, 'title.en', ''))],
                'expires_at' => $filled($r['expires_at'] ?? null) ? $r['expires_at'] : null,
            ])->values()->all();

        $supports = filter_var($this->input('supports_shipping', false), FILTER_VALIDATE_BOOLEAN);
        $everywhere = filter_var($this->input('ships_everywhere', true), FILTER_VALIDATE_BOOLEAN);
        $governorates = (! $supports || $everywhere) ? [] : collect((array) $this->input('shipping_governorate_ids', []))
            ->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all();

        $this->merge([
            'websites' => $websites,
            'social_links' => $social,
            'coupons' => $coupons,
            'supports_shipping' => $supports,
            'ships_everywhere' => ! $supports || $everywhere,
            'shipping_governorate_ids' => $governorates,
        ]);
    }

    protected function storeExtrasRules(): array
    {
        return [
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:store_categories,id'],
            'meta_title' => ['nullable', 'array'],
            'meta_title.*' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'array'],
            'meta_description.*' => ['nullable', 'string', 'max:160'],
            'meta_keywords' => ['nullable', 'array'],
            'meta_keywords.*' => ['nullable', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'ships_everywhere' => ['boolean'],
            'shipping_governorate_ids' => ['array', 'required_if:ships_everywhere,false'],
            'supports_shipping' => ['boolean'],
            'shipping_governorate_ids.*' => ['integer', 'exists:governorates,id'],
            'websites' => ['nullable', 'array', 'max:10'],
            'websites.*' => ['url', 'max:2048'],
            'social_links' => ['nullable', 'array', 'max:15'],
            'social_links.*.platform' => ['required', 'in:'.implode(',', self::SOCIAL_PLATFORMS)],
            'social_links.*.url' => ['required', 'url', 'max:2048'],
            'coupons' => ['nullable', 'array', 'max:50'],
            'coupons.*.code' => ['required', 'string', 'max:64'],
            'coupons.*.title' => ['nullable', 'array'],
            'coupons.*.title.*' => ['nullable', 'string', 'max:255'],
            'coupons.*.expires_at' => ['nullable', 'date'],
        ];
    }

    protected function storeExtrasMessages(): array
    {
        return [
            'shipping_governorate_ids.required_if' => 'Pick at least one governorate, or switch to "all governorates".',
            'websites.*.url' => 'Each website must be a full link starting with http:// or https://.',
            'social_links.*.url.url' => 'Each social link must be a full link starting with http:// or https://.',
            'coupons.*.code.required' => 'Each coupon needs a code.',
        ];
    }
}
