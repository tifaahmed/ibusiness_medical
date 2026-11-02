<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A role's monthly cap on one hand-written balance permission: the default for its holders. `monthly_limit` null = unlimited. */
class RoleLedgerLimit extends Model
{
    protected $fillable = ['role_id', 'permission', 'monthly_limit', 'updated_by'];

    protected function casts(): array
    {
        return ['monthly_limit' => 'decimal:2'];
    }
}
