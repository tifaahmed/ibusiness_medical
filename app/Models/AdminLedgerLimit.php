<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An admin's monthly cap on one hand-written balance permission. `monthly_limit` null = unlimited. */
class AdminLedgerLimit extends Model
{
    protected $fillable = ['user_id', 'permission', 'monthly_limit', 'updated_by'];

    protected function casts(): array
    {
        return ['monthly_limit' => 'decimal:2'];
    }
}
