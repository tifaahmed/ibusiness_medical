<?php

namespace App\Services\Otp;

use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Support\OtpSettings;
use App\Support\PhoneNumbers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * The one-time code behind the storefront's phone login.
 *
 * A member types their phone on the Deilar site, that site asks this
 * application for a code, and the member types back what arrived by SMS. Both
 * halves — issuing and checking — live here so the policy has one home: Deilar
 * is told a code was issued and whether one verified, and nothing else.
 *
 * Codes live in the CACHE, not in a table. They are worth ten minutes, they
 * have to expire on their own whether or not anybody comes back for them, and
 * a row per login attempt is a table that only ever grows. The trade is that a
 * flushed cache invalidates every code in flight, which costs the handful of
 * members mid-login one resend.
 *
 * What is stored is a HASH of the code, never the code: a cache readable by
 * anything on the box must not be a list of live credentials.
 */
class MemberOtp
{
    /**
     * How long a member has to wait before asking for another code.
     *
     * Long enough that a resend button cannot be used to send somebody a
     * message a second; short enough that a member who genuinely did not
     * receive one is not stuck looking at a countdown.
     */
    public const RESEND_AFTER_SECONDS = 60;

    /**
     * Wrong codes allowed against one issue before it is thrown away.
     *
     * Four digits is 10,000 combinations, so the attempt count is what makes a
     * short code safe. Burning the issue rather than locking the phone means a
     * member who fat-fingered it five times can simply ask for a new one.
     */
    public const MAX_ATTEMPTS = 5;

    private const CACHE_PREFIX = 'member-otp:';

    public function __construct(private SmsGateway $sms) {}

    /**
     * The member behind a typed phone number, or null if there is none.
     *
     * "Member" means a user with a membership of any kind — a real card, or
     * the inert pending one a self-registration gets. That is also what keeps
     * staff out of the storefront login: the accounts holding an admin role
     * hold no membership, so they simply do not resolve, and a login code can
     * never be a way into an account it was not meant for.
     */
    public function findMember(string $phone): ?User
    {
        $national = PhoneNumbers::national($phone);

        if ($national === null) {
            return null;
        }

        return User::query()
            /*
             * `memberships`, not `membership`. The singular relation is scoped
             * to ACTIVE cards, and a self-registration starts inactive — gating
             * the login on it would sign somebody up and then refuse to let
             * them back in, which is the one thing this must not do.
             */
            ->whereHas('memberships')
            ->with('membership')
            /*
             * Rows have been keyed in over years and some carry a country code
             * or a space. Comparing the digits of both sides is what makes a
             * number typed as +20 106 258 7475 find one stored as 01062587475.
             */
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') IN (?, ?, ?)",
                [$national, ltrim($national, '0'), '20'.ltrim($national, '0')]
            )
            ->first();
    }

    /**
     * The fixed code set on the member behind `$phone`, or null when there is
     * no such member or nobody has given them one.
     *
     * Digits only, and an empty result reads as null: a column holding spaces
     * or a stray letter must not become a code nobody can type. Cleared back
     * to null, the member simply follows the site setting again.
     */
    private function memberFixedCode(string $phone): ?string
    {
        $raw = (string) ($this->findMember($phone)?->otp_fixed_code ?? '');

        $code = preg_replace('/\D+/', '', $raw) ?? '';

        return $code === '' ? null : $code;
    }

    /**
     * Issue a code for `$phone` and, when SMS is on, send it there.
     *
     * Keyed on the PHONE rather than on a member, because an unknown number
     * gets a code too: the storefront registers whoever answers one, so there
     * is no account to hang the issue off until after it has been verified.
     *
     * @return array{
     *     delivery: 'sms'|'fixed',
     *     expires_in: int,
     *     resend_after: int,
     *     length: int,
     *     error: ?string,
     * } `error` non-null means nothing was issued
     */
    public function issue(string $phone): array
    {
        /*
         * A member carrying their own code is exempt from the whole policy:
         * nothing is sent, and that code is what verifies. Checked before the
         * site setting because it exists precisely to differ from it — the
         * site can be sending real codes by SMS while this one member is not.
         */
        $memberCode = $this->memberFixedCode($phone);

        $bySms = $memberCode === null && OtpSettings::deliversBySms();

        $code = $memberCode ?? ($bySms ? $this->generateCode() : OtpSettings::fixedCode());

        if ($code === null) {
            /*
             * SMS is off and the fixed code has been cleared. That is an
             * administrator having closed the login, not a fault, so it is
             * reported rather than papered over with a default nobody chose.
             */
            return $this->failure('No login code is configured.');
        }

        $ttl = OtpSettings::ttlMinutes() * 60;

        if ($bySms) {
            $result = $this->sms->send($phone, OtpSettings::message($code));

            if (! $result['sent']) {
                return $this->failure($result['error'] ?? 'The code could not be sent.');
            }
        }

        Cache::put($this->key($phone), [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'issued_at' => now()->timestamp,
            'delivery' => $bySms ? 'sms' : 'fixed',
        ], $ttl);

        return [
            'delivery' => $bySms ? 'sms' : 'fixed',
            'expires_in' => $ttl,
            'resend_after' => self::RESEND_AFTER_SECONDS,
            'length' => strlen($code),
            'error' => null,
        ];
    }

    /**
     * How many seconds are left on this number's resend cooldown, 0 when
     * another code may be asked for now.
     *
     * Read from the issue itself rather than from a second key: one write, one
     * expiry, and no way for the two to disagree about whether a code exists.
     */
    public function secondsUntilResend(string $phone): int
    {
        $issue = Cache::get($this->key($phone));

        if (! is_array($issue)) {
            return 0;
        }

        $elapsed = now()->timestamp - (int) ($issue['issued_at'] ?? 0);

        return max(0, self::RESEND_AFTER_SECONDS - $elapsed);
    }

    /**
     * Check `$code` against the issue held for `$phone`.
     *
     * A correct code is consumed on the way out: an issue verifies exactly
     * once, so a code read over somebody's shoulder is worth nothing the moment
     * it has been used.
     *
     * @return array{verified: bool, reason: ?string, attempts_left: int}
     */
    public function verify(string $phone, string $code): array
    {
        $key = $this->key($phone);
        $issue = Cache::get($key);

        if (! is_array($issue) || ! isset($issue['hash'])) {
            return $this->rejection('expired');
        }

        $code = PhoneNumbers::foldDigits(trim($code));

        if (! Hash::check($code, (string) $issue['hash'])) {
            $attempts = (int) ($issue['attempts'] ?? 0) + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::forget($key);

                return $this->rejection('too_many_attempts');
            }

            $issue['attempts'] = $attempts;

            /*
             * Written back with the REMAINING life, not a fresh window. A wrong
             * guess must not be a way to keep a code alive indefinitely.
             */
            Cache::put($key, $issue, $this->remainingSeconds($issue));

            return $this->rejection('invalid', self::MAX_ATTEMPTS - $attempts);
        }

        Cache::forget($key);

        return [
            'verified' => true,
            'reason' => null,
            'attempts_left' => self::MAX_ATTEMPTS,
        ];
    }

    /**
     * A member's number as it should be shown back to them: enough to confirm
     * they typed the right one, not enough to read off a shoulder.
     */
    public function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', PhoneNumbers::foldDigits($phone)) ?? '';

        if (strlen($digits) <= 4) {
            return $digits;
        }

        return str_repeat('•', strlen($digits) - 4).substr($digits, -4);
    }

    /**
     * A fresh code of the configured length.
     *
     * `random_int` rather than `rand`: this is a credential, and a predictable
     * one is no credential at all. Leading zeros are kept — a four-digit code
     * that can never start with 0 is a three-and-a-bit-digit code.
     */
    private function generateCode(): string
    {
        $length = OtpSettings::length();

        return str_pad(
            (string) random_int(0, (10 ** $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * One issue per NUMBER, keyed on its normalised form so `+20 106 258 7475`
     * and `01062587475` cannot hold two live codes between them.
     *
     * The number rather than a member id, because a code is issued before it is
     * known whether there is a member — an unknown number is a registration
     * waiting to happen, not a refusal.
     */
    private function key(string $phone): string
    {
        return self::CACHE_PREFIX.(PhoneNumbers::national($phone) ?? trim($phone));
    }

    /**
     * What is left of an issue's original window, at least a second.
     *
     * @param  array<string, mixed>  $issue
     */
    private function remainingSeconds(array $issue): int
    {
        $elapsed = now()->timestamp - (int) ($issue['issued_at'] ?? 0);

        return max(1, (OtpSettings::ttlMinutes() * 60) - $elapsed);
    }

    /**
     * @return array{delivery: 'sms', expires_in: int, resend_after: int, length: int, error: string}
     */
    private function failure(string $error): array
    {
        return [
            'delivery' => 'sms',
            'expires_in' => 0,
            'resend_after' => self::RESEND_AFTER_SECONDS,
            'length' => OtpSettings::length(),
            'error' => $error,
        ];
    }

    /**
     * @return array{verified: false, reason: string, attempts_left: int}
     */
    private function rejection(string $reason, int $attemptsLeft = 0): array
    {
        return [
            'verified' => false,
            'reason' => $reason,
            'attempts_left' => max(0, $attemptsLeft),
        ];
    }
}
