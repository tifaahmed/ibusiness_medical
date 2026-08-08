<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaService
{
    public function uploadImage(HasMedia $model, UploadedFile $image, string $collection = 'avatar', ?string $disk = null): string
    {
        // Use 'public' disk by default for images that need to be publicly accessible
        $disk = $disk ?? 'public';

        $model->clearMediaCollection($collection);

        $mediaItem = $model->addMedia($image)
            ->usingName($this->generateUniqueFilename($image))
            ->toMediaCollection($collection, $disk);

        return $mediaItem->getUrl();
    }

    /**
     * Upload image from a file path (for use in queued jobs).
     *
     * @param HasMedia $model
     * @param string $filePath
     * @param string $collection
     * @param string|null $disk
     * @return string
     */
    public function uploadImageFromPath(HasMedia $model, string $filePath, string $collection = 'avatar', ?string $disk = null): string
    {
        // Use 'public' disk by default for images that need to be publicly accessible
        $disk = $disk ?? 'public';

        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $model->clearMediaCollection($collection);

        $mediaItem = $model->addMedia($filePath)
            ->usingName($this->generateUniqueFilenameFromPath($filePath))
            ->toMediaCollection($collection, $disk);

        return $mediaItem->getUrl();
    }

    /**
     * Append multiple images to a multi-file collection without clearing
     * existing items. Returns the list of new URLs.
     *
     * @param HasMedia $model
     * @param array<int, UploadedFile> $images
     * @param string $collection
     * @param string|null $disk
     * @return array<int, string>
     */
    public function appendImages(HasMedia $model, array $images, string $collection, ?string $disk = null): array
    {
        $disk = $disk ?? 'public';
        $urls = [];

        foreach ($images as $image) {
            if (!$image instanceof UploadedFile) {
                continue;
            }
            $mediaItem = $model->addMedia($image)
                ->usingName($this->generateUniqueFilename($image))
                ->toMediaCollection($collection, $disk);
            $urls[] = $mediaItem->getUrl();
        }

        return $urls;
    }

    /**
     * Delete specific media items from a collection by their IDs. Items that
     * do not belong to the given model/collection are silently skipped.
     *
     * @param HasMedia $model
     * @param array<int, int|string> $mediaIds
     * @param string $collection
     * @return int Number of items deleted.
     */
    public function deleteMediaByIds(HasMedia $model, array $mediaIds, string $collection): int
    {
        if (empty($mediaIds)) {
            return 0;
        }

        $deleted = 0;
        $items = $model->getMedia($collection)->whereIn('id', $mediaIds);
        foreach ($items as $item) {
            if ($item->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function deleteImage(HasMedia $model, ?string $url, string $collection = 'avatar'): bool
    {
        if (empty($url)) {
            return false;
        }

        $media = $model->getMedia($collection)
            ->first(function ($item) use ($url) {
                return $item->getUrl() === $url || str_contains($item->getUrl(), basename($url));
            });

        if ($media) {
            return $media->delete();
        }

        try {
            $model->clearMediaCollection($collection);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return 'image_' . time() . '_' . uniqid() . '.' . $extension;
    }

    private function generateUniqueFilenameFromPath(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        return 'image_' . time() . '_' . uniqid() . '.' . $extension;
    }
}
