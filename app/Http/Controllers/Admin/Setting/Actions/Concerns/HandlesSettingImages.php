<?php

namespace App\Http\Controllers\Admin\Setting\Actions\Concerns;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Storing and clearing the file behind an `image` setting.
 *
 * The value of an image row is a path on the public disk, not a link, so the
 * upload has to be written somewhere and the old one cleaned up. Only files
 * this application put in `settings/` are ever deleted — a row pointing at an
 * asset shipped under public/ (the bundled logo) names a file that belongs to
 * the deploy, and removing it would break every other page using it.
 */
trait HandlesSettingImages
{
    /**
     * Store an upload and return the path to save as the setting's value.
     */
    protected function storeImage(UploadedFile $file): string
    {
        return $file->store(Setting::IMAGE_DIRECTORY, 'public');
    }

    /**
     * Delete a previously uploaded file, if that is what the path names.
     */
    protected function forgetImage(?string $path): void
    {
        $path = trim((string) $path);

        if ($path === '' || ! str_starts_with($path, Setting::IMAGE_DIRECTORY.'/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * The value an image row should end up holding.
     *
     * Returns the new upload's path, null when the admin cleared the image, or
     * the current value when they touched neither.
     *
     * The file being replaced is deliberately left on disk: it is still what
     * the row points at until the save goes through, and a save can fail. The
     * caller discards it afterwards through forgetReplacedImage().
     *
     * @param  array<string, mixed>  $validated
     */
    protected function resolveImageValue(array $validated, ?string $current): ?string
    {
        $upload = $validated['value_image'] ?? null;

        if ($upload instanceof UploadedFile) {
            return $this->storeImage($upload);
        }

        if (filter_var($validated['value_image_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return $current;
    }

    /**
     * Drop the file a saved row no longer points at.
     *
     * Called once the new value is safely stored, so a failed save never leaves
     * the row naming a file that has already been deleted.
     */
    protected function forgetReplacedImage(?string $previous, ?string $current): void
    {
        if ($previous === null || $previous === $current) {
            return;
        }

        $this->forgetImage($previous);
    }
}
