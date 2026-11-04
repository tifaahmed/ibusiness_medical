<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A staff note about a member. Everyone with `view member notes` reads it; only its author changes or deletes it. */
class MemberNote extends Model
{
    protected $fillable = ['user_id', 'created_by', 'body'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
