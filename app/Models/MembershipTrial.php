<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One event in a member's trial history. Append-only: nothing edits or deletes these rows. */
class MembershipTrial extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_STARTED = 'started';

    public const EVENT_EXTENDED = 'extended';

    public const EVENT_SHORTENED = 'shortened';

    public const EVENT_ENDED = 'ended';

    protected $fillable = [
        'membership_id', 'event', 'starts_on', 'ends_on', 'previous_ends_on', 'expires_on', 'previous_expires_on',
        'days', 'admin_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date', 'ends_on' => 'date', 'previous_ends_on' => 'date',
            'expires_on' => 'date', 'previous_expires_on' => 'date', 'created_at' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
