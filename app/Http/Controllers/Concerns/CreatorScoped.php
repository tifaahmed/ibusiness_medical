<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Generic creator-scoping helpers used by every "manage X / manage own X" pair.
 *
 * The pattern: a route is reachable when the admin has either permission, then
 * inside the controller we call {@see scopesToCreator()} to decide whether to
 * apply the per-row `created_by = auth()->id()` constraint.
 *
 * Each resource controller defines small accessors (`fullPermission()`,
 * `ownPermission()`) so the same helpers work across resources.
 */
trait CreatorScoped
{
    abstract protected function fullPermission(): string;
    abstract protected function ownPermission(): string;

    /**
     * True when the current admin is restricted to records they created.
     * Full permission wins — if both are somehow assigned, full access stands.
     */
    protected function scopesToCreator(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }
        if ($user->hasPermissionTo($this->fullPermission())) {
            return false;
        }
        return $user->hasPermissionTo($this->ownPermission());
    }

    /**
     * Apply the creator filter to a builder when scoping is in effect.
     */
    protected function applyCreatorScope($query, string $column = 'created_by')
    {
        if ($this->scopesToCreator()) {
            $query->where($column, Auth::id());
        }
        return $query;
    }

    /**
     * 403 if the model is not owned by the current admin and the admin is scoped.
     */
    protected function assertOwns(Model $model, string $column = 'created_by'): void
    {
        if (!$this->scopesToCreator()) {
            return;
        }
        if ((int) ($model->{$column} ?? 0) !== (int) Auth::id()) {
            throw new AuthorizationException('You can only manage records you created.');
        }
    }
}
