<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPayment extends Model
{
    protected $fillable = [
        'membership_id',
        'amount',
        'months_paid',
        'from_date',
        'to_date',
        'notes',
        'type',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'months_paid' => 'integer',
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
