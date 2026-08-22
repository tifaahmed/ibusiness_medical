<?php

namespace App\Http\Controllers\Admin\Facility\Migration\Concerns;

use App\Models\Sales;
use Illuminate\Database\Eloquent\Model;

/**
 * The dropdown shape the migration screen matches a package's lookups against.
 *
 * The page hands the whole list over on load and the quick-create endpoint hands
 * back single rows as they are made — both have to spell an option the same way,
 * or a freshly created city would not match the row that asked for it.
 */
trait LookupOptions
{
    /**
     * One dropdown option for a translatable lookup row.
     *
     * @return array<string, mixed>
     */
    protected function option(Model $model): array
    {
        $en = $model->getTranslation('name', 'en');
        $ar = $model->getTranslation('name', 'ar');

        return [
            'value' => $model->getKey(),
            'label' => $model->getTranslation('name', app()->getLocale()) ?: ($en ?: $ar),
            'name_en' => $en ?: null,
            'name_ar' => $ar ?: null,
            'slug' => $model->slug ?? null,
        ];
    }

    /**
     * One dropdown option for a sales rep. Both shapes the name column can
     * hold spell one readable label, or the picker would offer the operator raw
     * JSON to choose between — see Sales::nameTranslations().
     *
     * @return array<string, mixed>
     */
    protected function salesOption(Sales $sale): array
    {
        $translations = $this->salesTranslations($sale);

        return [
            'value' => $sale->getKey(),
            'label' => $this->salesLabel($sale),
            'name_en' => $translations['en'] ?? null,
            'name_ar' => $translations['ar'] ?? null,
            'slug' => null,
        ];
    }

    /**
     * The name to show, in the reader's locale when the row carries it.
     */
    protected function salesLabel(Sales $sale): string
    {
        return $sale->displayName();
    }

    /**
     * Every locale the row actually carries — falling back to the raw column
     * when it holds a bare name rather than a translation blob.
     *
     * @return array<string, string>
     */
    protected function salesTranslations(Sales $sale): array
    {
        return $sale->nameTranslations();
    }
}
