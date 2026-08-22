<?php

namespace App\Http\Resources\Admin\Concerns;

/**
 * Shared fallback for translatable fields on the admin "view" screens.
 *
 * Rows written before a field became translatable hold a bare string, and
 * getTranslations() then answers an empty array — which would blank the field
 * out on the page. Show the raw value under both locales instead so nothing
 * disappears from an older record.
 */
trait ResolvesTranslations
{
    /**
     * @return array<string, string>
     */
    protected function translationMap(string $field): array
    {
        $stored = array_filter(
            $this->getTranslations($field),
            fn ($value) => trim((string) $value) !== ''
        );

        if ($stored !== []) {
            return array_map(fn ($value) => (string) $value, $stored);
        }

        $raw = trim((string) $this->getRawOriginal($field));

        return $raw !== '' ? ['ar' => $raw, 'en' => $raw] : [];
    }
}
