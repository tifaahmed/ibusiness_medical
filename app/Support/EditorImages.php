<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Images dropped into a rich-text description.
 *
 * They are uploaded the moment they are added (the editor needs a URL at once)
 * into a per-entity "editor" directory, tied to the entity as hidden gallery
 * rows when the form is saved, and referenced from the description by a
 * host-less URL ("/storage/…") so the same row works whatever domain serves it.
 */
final class EditorImages
{
    /** scope => directory on the public disk. */
    public const DIRECTORIES = [
        'product' => 'products/gallery/editor',
        'store' => 'stores/gallery/editor',
        'facility' => 'facilities/gallery/editor',
    ];

    public static function directory(string $scope): string
    {
        return self::DIRECTORIES[$scope] ?? self::DIRECTORIES['product'];
    }

    /** Only paths the upload endpoint could have produced for this scope. */
    public static function isEditorPath(string $path, string $scope): bool
    {
        return str_starts_with($path, self::directory($scope).'/') && ! str_contains($path, '..');
    }

    /** Is this gallery path one of the hidden description images (any scope)? */
    public static function isAnyEditorPath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        foreach (self::DIRECTORIES as $dir) {
            if (str_starts_with($path, $dir.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function url(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    /** "https://host/storage/x.png" → "/storage/x.png" inside <img src>. */
    public static function relativise(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return preg_replace('#(<img\b[^>]*?\bsrc\s*=\s*["\'])https?://[^/"\']+(/storage/)#i', '$1$2', $html);
    }

    /** Apply {@see relativise()} to every locale of a translatable attribute. */
    public static function relativiseTranslations(Model $model, string $attribute): void
    {
        if (! method_exists($model, 'getTranslations') || ! $model->isDirty($attribute)) {
            return;
        }

        foreach ($model->getTranslations($attribute) as $locale => $html) {
            if (is_string($html)) {
                $model->setTranslation($attribute, $locale, self::relativise($html));
            }
        }
    }
}
