<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * The staff phone that gets an SMS when a stranger fills in a contact form.
 *
 * One row in `settings` (`contact_alert_phone`, edited at /admin/setting).
 * Blank = no alert, which is also the default: nothing is sent until an
 * administrator says where it should go.
 *
 * "Stranger" means no live user holds that number. A member who writes in is
 * already known to customer services; a number we have never seen is a lead
 * that goes cold if nobody rings it quickly.
 */
final class ContactAlert
{
    public const PHONE = 'contact_alert_phone';

    /** The phone to alert, or null when none is set. */
    public static function phone(): ?string
    {
        $phone = trim((string) SiteSettings::get(self::PHONE, ''));

        return $phone === '' ? null : $phone;
    }

    /**
     * Whether a live user holds this number. Matched on the last 10 digits so
     * +201012345678, 201012345678 and 01012345678 are the same person.
     */
    public static function isKnownPhone(string $phone): bool
    {
        $digits = substr(preg_replace('/\D+/', '', $phone) ?? '', -10);

        if (strlen($digits) < 10) {
            return false;
        }

        return DB::table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('phone')
            ->whereRaw('right(phone, 10) = ?', [$digits])
            ->exists();
    }

    /**
     * Whether this is the first enquiry from the number: no other row in
     * `contact_messages` has the same last 10 digits. Someone who writes five
     * times rings the staff phone once.
     */
    public static function isFirstEnquiry(string $phone, int $exceptId): bool
    {
        $digits = substr(preg_replace('/\D+/', '', $phone) ?? '', -10);

        if (strlen($digits) < 10) {
            return true;
        }

        return ! DB::table('contact_messages')
            ->where('id', '!=', $exceptId)
            ->whereNotNull('phone')
            ->whereRaw('right(phone, 10) = ?', [$digits])
            ->exists();
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::PHONE, 'name' => ['en' => 'Phone to SMS when a non-member contacts us (blank = off)', 'ar' => 'رقم يصله SMS عند تواصل شخص غير عضو (فارغ = متوقف)'], 'value' => '', 'value_type' => Setting::TYPE_PHONE],
        ];
    }
}
