<?php

namespace App\Models\Concerns;

use App\Support\EditorImages;

/**
 * Keeps description images as host-less "/storage/…" URLs on save. A model
 * lists its rich-text attributes in `$editorHtmlAttributes`.
 */
trait RelativisesEditorHtml
{
    public static function bootRelativisesEditorHtml(): void
    {
        static::saving(function ($model) {
            foreach ($model->editorHtmlAttributes ?? [] as $attribute) {
                EditorImages::relativiseTranslations($model, $attribute);
            }
        });
    }
}
