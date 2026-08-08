<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerOfferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_offer_id',
        'phone_number',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function partnerOffer(): BelongsTo
    {
        return $this->belongsTo(PartnerOffer::class);
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }
}
