<?php

namespace App\Models;

use App\Enums\Contact\ContactLogActionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded change against an enquiry.
 *
 * `admin_id` is null for the opening `received` row: that one is written by a
 * visitor submitting a public form, not by anybody with a login.
 *
 * A salesperson is logged by NAME and a status by its VALUE, and the asymmetry
 * is deliberate — a name has to survive that salesperson being deleted, while
 * a status has to be re-translated for whoever reads the log later.
 */
class ContactMessageLog extends Model
{
    protected $fillable = [
        'contact_message_id',
        'admin_id',
        'action',
        'old_value',
        'new_value',
    ];

    public function contactMessage(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ContactLogActionEnum::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
