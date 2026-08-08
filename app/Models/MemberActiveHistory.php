<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberActiveHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'membership_id',
        'old_is_active',
        'new_is_active',
        'changed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_is_active' => 'boolean',
            'new_is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
