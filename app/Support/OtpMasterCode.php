<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * The master login code: a code the top admin chooses that is accepted IN ADDITION to the real SMS code.
 *
 * Off by default. Stored hashed (nobody can read it back), optional expiry, every change and every use
 * is audited in `otp_master_code_logs`. It proves nothing about the phone, so it never marks one verified.
 */
final class OtpMasterCode
{
    public const ENABLED = 'otp_master_enabled';

    public const HASH = 'otp_master_code_hash';

    public const LENGTH = 'otp_master_code_length';

    public const EXPIRES_AT = 'otp_master_expires_at';

    public static function active(): bool
    {
        if (SiteSettings::get(self::ENABLED, false) !== true || (string) SiteSettings::get(self::HASH, '') === '') {
            return false;
        }

        $expires = (string) SiteSettings::get(self::EXPIRES_AT, '');

        return $expires === '' || now()->lt($expires);
    }

    public static function matches(string $code): bool
    {
        return self::active() && Hash::check($code, (string) SiteSettings::get(self::HASH, ''));
    }

    /** @return array{enabled: bool, active: bool, length: ?int, expires_at: ?string} */
    public static function summary(): array
    {
        $expires = (string) SiteSettings::get(self::EXPIRES_AT, '');

        return [
            'enabled' => SiteSettings::get(self::ENABLED, false) === true,
            'active' => self::active(),
            'length' => (int) SiteSettings::get(self::LENGTH, 0) ?: null,
            'expires_at' => $expires === '' ? null : $expires,
        ];
    }

    /** Turn it on with a new code (or keep the stored one when `$code` is null), or turn it off. */
    public static function configure(bool $enabled, ?string $code, ?string $expiresAt, ?int $adminId, ?string $ip): void
    {
        $name = ['en' => 'Master login code', 'ar' => 'كود الدخول الرئيسي'];

        if ($code !== null) {
            SiteSettings::put(self::HASH, Hash::make($code), Setting::TYPE_STRING, $name);
            SiteSettings::put(self::LENGTH, (string) strlen($code), Setting::TYPE_NUMBER, $name);
        }

        SiteSettings::put(self::ENABLED, $enabled, Setting::TYPE_BOOLEAN, $name);
        SiteSettings::put(self::EXPIRES_AT, $enabled ? ($expiresAt ?? '') : '', Setting::TYPE_STRING, $name);

        self::audit($enabled ? ($code !== null ? 'changed' : 'enabled') : 'disabled', $adminId, $enabled ? $expiresAt : null, null, $ip);
    }

    public static function audit(string $action, ?int $adminId, ?string $expiresAt, ?string $phoneMasked, ?string $ip): void
    {
        DB::table('otp_master_code_logs')->insert([
            'admin_id' => $adminId,
            'action' => $action,
            'expires_at' => $expiresAt,
            'phone_masked' => $phoneMasked,
            'ip' => $ip,
            'created_at' => now(),
        ]);

        Log::warning('Master login code '.$action, ['by' => $adminId, 'phone' => $phoneMasked]);
    }
}
