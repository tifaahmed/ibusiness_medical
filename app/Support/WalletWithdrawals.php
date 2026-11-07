<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use App\Services\Paymob\PaymobPayouts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cashing wallet money out to Vodafone Cash (Paymob Payouts). Every rule lives here; clients relay and draw.
 *
 *   wallet_withdrawals_enabled   the switch (default OFF — nothing is offered or sent while it is off)
 *   wallet_withdrawal_min        smallest request, EGP (default 50)
 *   wallet_withdrawal_max        largest single request, EGP (default 2000)
 *   wallet_withdrawal_daily_max  most a member may have requested in 24 h, EGP (default 5000)
 *   wallet_withdrawal_auto       pay at once without an admin (default OFF: the top admin approves each request)
 *
 * Money rules (the wallet NEVER goes below zero, money is never paid or returned twice):
 *  - the wallet is debited in the SAME transaction that records the request (row locked) and only if it holds the whole amount;
 *  - `pending` → the top admin approves (or rejects / the member cancels) → `processing` → Paymob answers → `paid` | `failed`;
 *  - a request that ends WITHOUT money leaving (rejected, canceled, failed, not sent) gives the amount back to the wallet ONCE
 *    (`refunded_at` + a unique wallet key);
 *  - when we cannot tell what Paymob did (timeout, 5xx) the request STAYS `processing`: it is never refunded on a guess — an admin
 *    asks Paymob (`refresh`) with the same reference, which Paymob has never seen twice;
 *  - the same `client_reference` is sent on every attempt, so a retry cannot pay twice.
 */
final class WalletWithdrawals
{
    public const ENABLED = 'wallet_withdrawals_enabled';

    public const MIN = 'wallet_withdrawal_min';

    public const MAX = 'wallet_withdrawal_max';

    public const DAILY_MAX = 'wallet_withdrawal_daily_max';

    public const AUTO = 'wallet_withdrawal_auto';

    /** A request that Paymob has no record of may be given back only after this many minutes (so a slow index is not mistaken for "never sent"). */
    public const NOT_FOUND_GRACE_MINUTES = 15;

    public static function enabled(): bool
    {
        return (bool) SiteSettings::get(self::ENABLED, false);
    }

    public static function min(): float
    {
        return max(1.0, round((float) SiteSettings::get(self::MIN, 50), 2));
    }

    public static function max(): float
    {
        return max(self::min(), round((float) SiteSettings::get(self::MAX, 2000), 2));
    }

    public static function dailyMax(): float
    {
        return max(self::max(), round((float) SiteSettings::get(self::DAILY_MAX, 5000), 2));
    }

    public static function auto(): bool
    {
        return (bool) SiteSettings::get(self::AUTO, false);
    }

    /** What the app needs to draw the form (never decides anything itself). */
    public static function summary(): array
    {
        return ['enabled' => self::enabled() && app(PaymobPayouts::class)->configured(), 'min' => self::min(), 'max' => self::max(), 'daily_max' => self::dailyMax(), 'auto' => self::auto(), 'method' => 'vodafone_cash'];
    }

    /** A Vodafone Cash number: Egyptian mobile starting 010. Returns the 11 digits or null. */
    public static function vodafoneNumber(string $raw): ?string
    {
        $national = PhoneNumbers::national($raw);

        return $national !== null && preg_match('/^010\d{8}$/', $national) === 1 ? $national : null;
    }

    /**
     * Member asks to cash out.
     *
     * @return array{ok: bool, reason: ?string, withdrawal?: WalletWithdrawal}
     *                                                                         reason: disabled | bad_amount | bad_number | bad_national_id | not_enough | daily_limit | open_request
     */
    public static function request(User $user, float $amount, string $msisdn, string $nationalId): array
    {
        $amount = round($amount, 2);
        $phone = self::vodafoneNumber($msisdn);
        $nid = preg_replace('/\D+/', '', PhoneNumbers::foldDigits($nationalId)) ?? '';

        if (! self::enabled() || ! app(PaymobPayouts::class)->configured()) {
            return ['ok' => false, 'reason' => 'disabled'];
        }
        if ($amount < self::min() || $amount > self::max()) {
            return ['ok' => false, 'reason' => 'bad_amount'];
        }
        if ($phone === null) {
            return ['ok' => false, 'reason' => 'bad_number'];
        }
        if (strlen($nid) !== 14) {
            return ['ok' => false, 'reason' => 'bad_national_id'];
        }

        $result = DB::transaction(function () use ($user, $amount, $phone, $nid) {
            /* The member row is the lock for every wallet movement: two requests at once cannot spend the same pounds. */
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (WalletWithdrawal::query()->where('user_id', $user->id)->whereIn('status', [WalletWithdrawal::PENDING, WalletWithdrawal::PROCESSING])->exists()) {
                return ['ok' => false, 'reason' => 'open_request'];
            }

            $today = (float) WalletWithdrawal::query()->where('user_id', $user->id)->whereIn('status', WalletWithdrawal::COUNTED)->where('created_at', '>=', now()->subDay())->sum('amount');
            if ($today + $amount > self::dailyMax()) {
                return ['ok' => false, 'reason' => 'daily_limit'];
            }

            if (WalletTransaction::balanceOf($user->id) < $amount) {
                return ['ok' => false, 'reason' => 'not_enough'];
            }

            $withdrawal = WalletWithdrawal::query()->create([
                'user_id' => $user->id, 'amount' => $amount, 'msisdn' => $phone, 'national_id' => $nid,
                'status' => WalletWithdrawal::PENDING, 'client_reference' => (string) Str::uuid(),
            ]);
            $withdrawal->logEvent('requested');
            $withdrawal->save();

            WalletTransaction::query()->create([
                'user_id' => $user->id, 'source' => WalletTransaction::SOURCE_WITHDRAWAL, 'client_id' => 'withdrawal:'.$withdrawal->id,
                'amount' => -$amount, 'note' => 'cash out to Vodafone Cash '.$withdrawal->maskedMsisdn(),
            ]);

            return ['ok' => true, 'reason' => null, 'withdrawal' => $withdrawal];
        });

        if ($result['ok'] && self::auto()) {
            self::send($result['withdrawal']->id, null);
            $result['withdrawal'] = $result['withdrawal']->fresh();
        }

        return $result;
    }

    /** The member changes their mind while it is still waiting for approval. */
    public static function cancel(WalletWithdrawal $withdrawal, User $user): bool
    {
        return self::endWithoutPayment($withdrawal->id, WalletWithdrawal::CANCELED, 'canceled by member', null, [WalletWithdrawal::PENDING], $user->id);
    }

    /** The top admin refuses it: the wallet gets the money back. */
    public static function reject(WalletWithdrawal $withdrawal, int $adminId, ?string $reason = null): bool
    {
        return self::endWithoutPayment($withdrawal->id, WalletWithdrawal::REJECTED, $reason ?: 'rejected', $adminId, [WalletWithdrawal::PENDING]);
    }

    /** The top admin approves a pending request: it goes to Paymob. Safe to press twice (only `pending` can be sent). */
    public static function approve(WalletWithdrawal $withdrawal, int $adminId): bool
    {
        return self::send($withdrawal->id, $adminId);
    }

    /**
     * Asks Paymob where a `processing` request stands (by the reference we sent) and settles it. An answer of "no record" gives the money
     * back only after a grace period; "cannot tell" changes nothing.
     */
    public static function refresh(WalletWithdrawal $withdrawal, ?int $adminId = null): string
    {
        $row = WalletWithdrawal::query()->find($withdrawal->id);
        if ($row === null || $row->status !== WalletWithdrawal::PROCESSING) {
            return $row?->status ?? 'missing';
        }

        $answer = app(PaymobPayouts::class)->inquire($row->client_reference);

        if ($answer['outcome'] === 'not_found' && $row->decided_at !== null && $row->decided_at->lte(now()->subMinutes(self::NOT_FOUND_GRACE_MINUTES))) {
            $answer['outcome'] = 'failed';
            $answer['message'] = 'Paymob has no record of this payout.';
        }

        return self::settle($row->id, $answer, $adminId, [WalletWithdrawal::PROCESSING]);
    }

    private static function send(int $id, ?int $adminId): bool
    {
        /* Claim it: only one caller can move pending → processing. */
        $claimed = DB::transaction(function () use ($id, $adminId) {
            $row = WalletWithdrawal::query()->lockForUpdate()->find($id);
            if ($row === null || $row->status !== WalletWithdrawal::PENDING) {
                return null;
            }
            $row->forceFill(['status' => WalletWithdrawal::PROCESSING, 'decided_by' => $adminId, 'decided_at' => now()]);
            $row->logEvent($adminId ? 'approved' : 'auto_approved', $adminId);
            $row->save();

            return $row;
        });

        if ($claimed === null) {
            return false;
        }

        try {
            $answer = app(PaymobPayouts::class)->disburse($claimed->msisdn, (float) $claimed->amount, (string) $claimed->national_id, $claimed->client_reference, 'Deilar wallet cash out');
        } catch (Throwable $e) {
            Log::error('Withdrawal could not be sent.', ['withdrawal_id' => $id, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $answer = ['outcome' => 'unknown', 'transaction_id' => null, 'status' => null, 'code' => null, 'message' => 'Send failed.'];
        }

        self::settle($id, $answer, $adminId, [WalletWithdrawal::PROCESSING]);

        return true;
    }

    /** Writes Paymob's answer onto the request. success → paid; failed / not_sent → failed + money back once; pending / unknown → stays processing. */
    private static function settle(int $id, array $answer, ?int $adminId, array $from): string
    {
        $outcome = $answer['outcome'];

        if (in_array($outcome, ['failed', 'not_sent'], true)) {
            self::endWithoutPayment($id, WalletWithdrawal::FAILED, (string) ($answer['message'] ?? 'failed'), $adminId, $from, null, $answer);

            return WalletWithdrawal::FAILED;
        }

        return DB::transaction(function () use ($id, $answer, $outcome, $adminId, $from) {
            $row = WalletWithdrawal::query()->lockForUpdate()->find($id);
            if ($row === null || ! in_array($row->status, $from, true)) {
                return $row?->status ?? 'missing';
            }

            $row->forceFill(self::providerFields($answer));
            if ($outcome === 'success') {
                $row->forceFill(['status' => WalletWithdrawal::PAID, 'paid_at' => now()]);
                $row->logEvent('paid', $adminId, ['code' => $answer['code']]);
            } else {
                $row->logEvent($outcome === 'pending' ? 'pending_at_paymob' : 'unknown_answer', $adminId, ['code' => $answer['code']]);
            }
            $row->save();

            return $row->status;
        });
    }

    private static function providerFields(array $answer): array
    {
        return array_filter([
            'provider_transaction_id' => $answer['transaction_id'] ?? null, 'provider_status' => $answer['status'] ?? null,
            'provider_code' => $answer['code'] ?? null, 'provider_message' => $answer['message'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /** Ends a request with no money leaving and gives the amount back to the wallet — once. */
    private static function endWithoutPayment(int $id, string $status, string $why, ?int $adminId, array $from, ?int $memberId = null, array $answer = []): bool
    {
        return DB::transaction(function () use ($id, $status, $why, $adminId, $from, $memberId, $answer) {
            $row = WalletWithdrawal::query()->lockForUpdate()->find($id);
            if ($row === null || ! in_array($row->status, $from, true) || ($memberId !== null && (int) $row->user_id !== $memberId)) {
                return false;
            }

            User::query()->whereKey($row->user_id)->lockForUpdate()->first();

            $row->forceFill(['status' => $status, 'decided_at' => $row->decided_at ?? now(), 'decided_by' => $row->decided_by ?? $adminId] + self::providerFields($answer));
            $row->logEvent($status, $adminId, ['why' => mb_substr($why, 0, 160)]);

            if ($row->refunded_at === null) {
                WalletTransaction::query()->firstOrCreate(
                    ['user_id' => $row->user_id, 'client_id' => 'withdrawal-refund:'.$row->id],
                    ['source' => WalletTransaction::SOURCE_REFUND, 'amount' => (float) $row->amount, 'note' => 'cash out '.$status.' — money returned'],
                );
                $row->refunded_at = now();
            }
            $row->save();

            return true;
        });
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::ENABLED, 'name' => ['en' => 'Let members cash out the wallet to Vodafone Cash', 'ar' => 'السماح بسحب رصيد المحفظة إلى فودافون كاش'], 'value' => '0', 'value_type' => Setting::TYPE_BOOLEAN],
            ['slug' => self::MIN, 'name' => ['en' => 'Cash out: smallest request (EGP)', 'ar' => 'السحب: أقل مبلغ (ج.م)'], 'value' => '50', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::MAX, 'name' => ['en' => 'Cash out: largest single request (EGP)', 'ar' => 'السحب: أكبر مبلغ للطلب الواحد (ج.م)'], 'value' => '2000', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::DAILY_MAX, 'name' => ['en' => 'Cash out: most per member per 24 hours (EGP)', 'ar' => 'السحب: أقصى مبلغ للعضو في 24 ساعة (ج.م)'], 'value' => '5000', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::AUTO, 'name' => ['en' => 'Cash out: pay at once without admin approval', 'ar' => 'السحب: الصرف مباشرة بدون موافقة الأدمن'], 'value' => '0', 'value_type' => Setting::TYPE_BOOLEAN],
        ];
    }
}
