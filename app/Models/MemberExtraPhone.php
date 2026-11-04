<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Another number kept on a member — a NOTE, nothing more (for example the one that has WhatsApp). Any number per
 * member, not unique, never users.phone, never used to sign in or to send SMS.
 */
class MemberExtraPhone extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'phone', 'note', 'created_by'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The number as wa.me wants it: country code, digits only (01012345678 -> 201012345678). */
    public function whatsappDigits(): string
    {
        return self::toWhatsappDigits($this->phone);
    }

    public static function toWhatsappDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', \App\Support\PhoneNumbers::foldDigits($phone)) ?? '';
        $digits = preg_replace('/^00+/', '', $digits) ?? $digits;

        return str_starts_with($digits, '0') ? '20'.substr($digits, 1) : $digits;
    }
}
