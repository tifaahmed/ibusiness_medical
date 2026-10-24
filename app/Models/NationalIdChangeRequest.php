<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member's request to change the national ID on their card; only an admin applies it. */
class NationalIdChangeRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'membership_id', 'current_national_id', 'new_national_id', 'front_path', 'back_path',
        'note', 'status', 'contact_message_id', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
