<?php

namespace App\Http\Controllers\Admin\Setting\Actions\Store;

use App\Http\Controllers\Admin\Setting\Actions\Concerns\HandlesSettingImages;
use App\Models\Setting;

class StoreSettingAction
{
    use HandlesSettingImages;

    /**
     * Create a setting row.
     *
     * @param  array<string, mixed>  $validated
     */
    public function execute(array $validated): Setting
    {
        $type = $validated['value_type'];

        $value = $type === Setting::TYPE_IMAGE
            ? $this->resolveImageValue($validated, null)
            : $this->normalizeScalar($validated['value'] ?? null);

        // Saving the model clears the settings cache through its own events,
        // so a new row is readable immediately.
        return Setting::create([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'value_type' => $type,
            'value' => $value,
        ]);
    }

    /**
     * Blank and "not filled in" are the same thing for a setting: store null so
     * SiteSettings::get() falls back to its default instead of handing callers
     * an empty string.
     */
    protected function normalizeScalar(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
