<?php

namespace App\Http\Controllers\Admin\Setting\Actions\Update;

use App\Http\Controllers\Admin\Setting\Actions\Concerns\HandlesSettingImages;
use App\Models\Setting;

class UpdateSettingAction
{
    use HandlesSettingImages;

    /**
     * Update a setting row.
     *
     * @param  array<string, mixed>  $validated
     */
    public function execute(Setting $setting, array $validated): Setting
    {
        $wasImage = $setting->value_type === Setting::TYPE_IMAGE;
        $type = $validated['value_type'];
        // What the row points at now. Whatever happens below, this file stays
        // on disk until the new value is saved.
        $previous = $wasImage ? $setting->value : null;

        $value = $type === Setting::TYPE_IMAGE
            ? $this->resolveImageValue($validated, $previous)
            // Switching away from `image` leaves the uploaded file with nothing
            // pointing at it; it is cleaned up once the new value is stored.
            : $this->normalizeScalar($validated['value'] ?? null);

        $setting->update([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'value_type' => $type,
            'value' => $value,
        ]);

        $this->forgetReplacedImage($previous, $value);

        return $setting->refresh();
    }

    protected function normalizeScalar(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
