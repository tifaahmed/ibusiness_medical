<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Last line of defence for values that must belong to ONE live record: a phone
 * (users.phone) or a National ID (memberships.national_id).
 *
 * Form requests already say "already taken" nicely, but they only guard the
 * forms they are attached to. Imports, jobs, console commands, the app and
 * the website all reach `save()` without them, so the model refuses too.
 *
 * Only a value that is being SET or CHANGED is checked. Imported data already
 * holds shared values; an untouched legacy duplicate must stay editable so an
 * admin can open it and fix the OTHER field (or this one). Soft-deleted rows
 * do not count, matching the `unique` rules on the requests.
 */
trait GuardsUniqueIdentity
{
    /**
     * @param  string  $column   the column that must stay unique
     * @param  string  $message  shown to the caller
     */
    protected static function guardUnique(self $model, string $column, string $message): void
    {
        $value = $model->getAttribute($column);

        if ($value === null || $value === '') {
            return;
        }

        if ($model->exists && ! $model->isDirty($column)) {
            return;
        }

        $taken = static::query()
            ->where($column, $value)
            ->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))
            ->exists();

        if (! $taken) {
            return;
        }

        Log::warning('Duplicate identity refused', [
            'model' => static::class,
            'column' => $column,
            'id' => $model->getKey(),
            'user_id' => auth()->id(),
        ]);

        throw ValidationException::withMessages([$column => [$message]]);
    }
}
