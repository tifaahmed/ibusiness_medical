<?php

namespace App\Http\Controllers\Admin\Facility\Migration\Concerns;

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
}
