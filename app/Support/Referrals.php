<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\Referral;
use App\Models\ReferralClick;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Member-gets-member. The CODE is the referrer's card number (the share link, the QR and the typed field all carry it).
 *
 *   referral_enabled             the switch (default on)
 *   referral_signup_bonus        pounds the REFERRER gets in the wallet when a member ENTERS their code(default 10; once per member, paid when the code is ACCEPTED)
 *   PER PLAN (`month` | `half` | `year`, the keys of `MembershipPlan::plans()`), each its own admin setting:
 *   referral_discount_<plan>             pounds off the NEW member's first subscription IF it is that plan      (month 25 / half 50 / year 100)
 *   referral_commission_use_fixed_<plan> on = the referrer earns a FIXED sum for that plan, off = a percent    (default off)
 *   referral_commission_percent_<plan>   percent of what the member really paid for that plan                  (default 10)
 *   referral_commission_fixed_<plan>     pounds the referrer earns for that plan (never more than was paid)    (default 0)
 *   The old single `referral_discount` / `referral_commission_percent` settings are only the fallback when a per-plan row is missing.
 *   referral_signup_daily_limit  trial rewards per referrer per day (anti-farming; past it the reward is 0)      (default 10)
 *
 * Rules: one referral per new member, ever (unique `referred_user_id`); never yourself; the new member must not have paid for a
 * subscription already; the referrer must be a primary member with a real card. A tap on the link or an install pays NOTHING: the
 * trial reward is due only once the account exists, its phone was verified by a real SMS code and the trial started. Every payout is a
 * `referral_rewards` row (unique `reward_key`: `trial:<referral>` / `commission:<subscription>`) tied to one append-only
 * `wallet_transactions` row keyed the same way, so a repeat books once. A refund writes a reversal (a debit), never an edit.
 * A client never sends an amount — it relays a card number and draws the answer.
 */
final class Referrals
{
    public const ENABLED = 'referral_enabled';

    public const SIGNUP_BONUS = 'referral_signup_bonus';

    public const COMMISSION_PERCENT = 'referral_commission_percent';

    /** Legacy single discount: only the fallback for a plan whose own setting row is missing. */
    public const DISCOUNT = 'referral_discount';

    public const PLANS = ['month', 'half', 'year'];

    public const WINDOW_DAYS = 'referral_window_days';

    public const DAILY_LIMIT = 'referral_signup_daily_limit';

    public static function enabled(): bool
    {
        return (bool) SiteSettings::get(self::ENABLED, true);
    }

    public static function signupBonus(): float
    {
        return max(0.0, round((float) SiteSettings::get(self::SIGNUP_BONUS, 10), 2));
    }

    private static function planKey(?string $plan): string
    {
        return in_array($plan, self::PLANS, true) ? $plan : 'year';
    }

    /** Pounds off the new member's first subscription when it is plan `$plan`. */
    public static function discountFor(?string $plan): float
    {
        $slug = self::DISCOUNT.'_'.self::planKey($plan);

        return max(0.0, round((float) SiteSettings::get($slug, SiteSettings::get(self::DISCOUNT, 50)), 2));
    }

    /** The legacy "one discount" figure = the yearly plan's (what an old client that reads `referral_discount` expects). */
    public static function discount(): float
    {
        return self::discountFor('year');
    }

    /** Percent of a paid subscription of `$plan` the referrer earns (0–100) when that plan pays a percent. */
    public static function commissionPercentFor(?string $plan): float
    {
        $slug = self::COMMISSION_PERCENT.'_'.self::planKey($plan);

        return min(100.0, max(0.0, round((float) SiteSettings::get($slug, SiteSettings::get(self::COMMISSION_PERCENT, 10)), 2)));
    }

    public static function commissionFixedFor(?string $plan): float
    {
        return max(0.0, round((float) SiteSettings::get('referral_commission_fixed_'.self::planKey($plan), 0), 2));
    }

    public static function commissionUsesFixed(?string $plan): bool
    {
        return (bool) SiteSettings::get('referral_commission_use_fixed_'.self::planKey($plan), false);
    }

    /** Legacy: the yearly plan's percent. */
    public static function commissionPercent(): float
    {
        return self::commissionPercentFor('year');
    }

    /**
     * What the referrer earns for ONE paid subscription of `$plan` that cost `$paid`: a fixed sum (never more than was paid) or a percent.
     *
     * @return array{type: string, rate: float, amount: float}
     */
    public static function commissionFor(?string $plan, float $paid): array
    {
        if (self::commissionUsesFixed($plan)) {
            return ['type' => 'fixed', 'rate' => 0.0, 'amount' => round(min(self::commissionFixedFor($plan), max(0.0, $paid)), 2)];
        }

        $rate = self::commissionPercentFor($plan);

        return ['type' => 'percent', 'rate' => $rate, 'amount' => round($paid * $rate / 100, 2)];
    }

    /**
     * The rules per plan, for the clients to draw ("invite → your friend gets X off the yearly plan, you earn Y").
     *
     * @return array<string, array{discount: float, commission_type: string, commission_percent: float, commission_fixed: float}>
     */
    public static function rulesByPlan(): array
    {
        $rules = [];
        foreach (self::PLANS as $plan) {
            $rules[$plan] = [
                'discount' => self::discountFor($plan),
                'commission_type' => self::commissionUsesFixed($plan) ? 'fixed' : 'percent',
                'commission_percent' => self::commissionPercentFor($plan),
                'commission_fixed' => self::commissionFixedFor($plan),
            ];
        }

        return $rules;
    }

    /** Referrals per referrer per 24 hours that still pay the sign-up reward; the rest are recorded (the new member keeps the discount) but pay 0. */
    public static function dailyLimit(): int
    {
        return max(0, (int) SiteSettings::get(self::DAILY_LIMIT, 10));
    }

    public static function windowDays(): int
    {
        return max(1, (int) SiteSettings::get(self::WINDOW_DAYS, 30));
    }

    /**
     * Every rule for "may this member enter this code", WITHOUT writing anything (the confirm step in the profile uses it, then `claim`
     * runs the same checks again). There is NO expiry: an old account that never subscribed may still enter a code.
     *
     * @return array{ok: bool, reason: ?string, card: ?Membership}
     */
    public static function check(User $member, string $number): array
    {
        if (! self::enabled()) {
            return ['ok' => false, 'reason' => 'disabled', 'card' => null];
        }

        /* Only individuals can enter a code: a company card never gets the referral discount. */
        if (Membership::query()->where('user_id', $member->id)->where('membership_number', 'not like', 'REG-%')->where('member_kind', Membership::KIND_COMPANY)->exists()) {
            return ['ok' => false, 'reason' => 'company', 'card' => null];
        }

        $number = trim($number);
        $referrerCard = $number === '' || str_starts_with(strtoupper($number), 'REG-') ? null : Membership::query()
            ->where('membership_number', $number)
            ->where('member_type', Membership::TYPE_PRIMARY)
            ->first();

        if ($referrerCard === null || $referrerCard->user === null) {
            return ['ok' => false, 'reason' => 'not_found', 'card' => null];
        }

        $referrer = $referrerCard->user;

        if ((int) $referrer->id === (int) $member->id || (filled($member->phone) && $member->phone === $referrer->phone)) {
            return ['ok' => false, 'reason' => 'self', 'card' => null];
        }

        if (Referral::query()->where('referred_user_id', $member->id)->exists()) {
            return ['ok' => false, 'reason' => 'already_referred', 'card' => null];
        }

        /* Two members cannot bring each other: that would pay both for nothing. */
        if (Referral::query()->where('referrer_user_id', $member->id)->where('referred_user_id', $referrer->id)->exists()) {
            return ['ok' => false, 'reason' => 'mutual', 'card' => null];
        }

        $paid = MembershipSubscription::query()
            ->where('status', MembershipSubscription::STATUS_ACCEPTED)
            ->whereIn('membership_id', Membership::withTrashed()->where('user_id', $member->id)->select('id'))
            ->exists();
        if ($paid) {
            return ['ok' => false, 'reason' => 'already_paid', 'card' => null];
        }

        return ['ok' => true, 'reason' => null, 'card' => $referrerCard];
    }

    /**
     * Records that `$member` came through the card number `$number`.
     *
     * @return array{ok: bool, reason: ?string, referral?: Referral}
     *                                                               reason: disabled | already_referred | not_found | self | mutual | already_paid | company
     */
    public static function claim(User $member, string $number, string $source = Referral::SOURCE_LINK): array
    {
        $check = self::check($member, $number);
        if (! $check['ok']) {
            return ['ok' => false, 'reason' => $check['reason']];
        }

        $referrerCard = $check['card'];
        $referrer = $referrerCard->user;

        try {
            $referral = DB::transaction(function () use ($member, $referrer, $referrerCard, $source) {
                $referral = Referral::query()->create([
                    'referrer_user_id' => $referrer->id,
                    'referrer_membership_id' => $referrerCard->id,
                    'referrer_number' => $referrerCard->membership_number,
                    'referred_user_id' => $member->id,
                    'source' => in_array($source, [Referral::SOURCE_CARD, Referral::SOURCE_ADMIN], true) ? $source : Referral::SOURCE_LINK,
                    'discount_amount' => max(array_map(self::discountFor(...), self::PLANS)),
                ]);

                return $referral;
            });
        } catch (QueryException) {
            /* Two requests at once: the unique index on `referred_user_id` let only one in. */
            return ['ok' => false, 'reason' => 'already_referred'];
        }

        /* The referrer's reward is due the moment the code is accepted (if the member's phone was verified by a real SMS code; otherwise it waits for that). */
        self::trialStarted($member);
        self::settleOnClaim($referral, $source === Referral::SOURCE_ADMIN);

        return ['ok' => true, 'reason' => null, 'referral' => $referral->fresh()];
    }

    /** Hours a tapped link can still be matched to the app that opens afterwards. */
    public const CLICK_HOURS = 24;

    private static function ipHash(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    /** A real, shareable code: a primary member's card number (never a `REG-` placeholder). */
    public static function codeExists(string $code): bool
    {
        $code = trim($code);

        return $code !== '' && ! str_starts_with(strtoupper($code), 'REG-')
            && Membership::query()->where('membership_number', $code)->where('member_type', Membership::TYPE_PRIMARY)->exists();
    }

    /** The website saw a visitor tap `/r/<code>`; remember it by IP hash so the app can find it after the install. */
    public static function recordClick(string $code, string $ip): bool
    {
        if (! self::enabled() || ! self::codeExists($code) || $ip === '') {
            return false;
        }

        ReferralClick::query()->create(['code' => trim($code), 'ip_hash' => self::ipHash($ip)]);

        return true;
    }

    /**
     * The app opened for the first time at `$ip`: the code tapped from this IP in the last day — only when it is UNAMBIGUOUS (one code,
     * not yet matched). Two different codes from one IP (a shared connection) match nothing rather than guessing. A match is used once.
     */
    public static function matchClick(string $ip): ?string
    {
        if (! self::enabled() || $ip === '') {
            return null;
        }

        return DB::transaction(function () use ($ip) {
            $clicks = ReferralClick::query()->where('ip_hash', self::ipHash($ip))->whereNull('matched_at')
                ->where('created_at', '>=', now()->subHours(self::CLICK_HOURS))->lockForUpdate()->get();

            if ($clicks->pluck('code')->unique()->count() !== 1) {
                return null;
            }

            ReferralClick::query()->whereIn('id', $clicks->pluck('id'))->update(['matched_at' => now()]);

            return (string) $clicks->first()->code;
        });
    }

    /** The pounds still to be taken off this card's FIRST subscription IF it is plan `$plan` (0 when there is no unused referral). */
    public static function pendingDiscount(?Membership $membership, ?string $plan = null): float
    {
        if ($membership === null || ! self::enabled()) {
            return 0.0;
        }

        $referral = Referral::query()->where('referred_user_id', $membership->user_id)->whereNull('subscribed_at')->first();

        return $referral === null ? 0.0 : self::discountFor($plan);
    }

    /** `$price` after the member's referral discount; never below 1 pound, so a discount cannot make a card free. */
    public static function apply(float $price, ?Membership $membership, ?string $plan = null): float
    {
        $discount = self::pendingDiscount($membership, $plan);

        return $discount > 0 && $price > 1 ? max(1.0, round($price - $discount, 2)) : $price;
    }

    /** True once the member holds a started trial card (a trial the member began; an admin-made or paid card does not count). */
    private static function hasStartedTrial(User $user): bool
    {
        return Membership::withTrashed()->where('user_id', $user->id)->whereNotNull('trial_started_at')
            ->where('membership_number', 'not like', 'REG-%')->exists();
    }

    /**
     * The new member started a trial (`StartTrialCard`) or told us who brought them after having started one. Stamps the referral and
     * pays the referrer's trial reward when it is due. Safe to call any number of times; never throws.
     */
    public static function trialStarted(User $member): void
    {
        try {
            $referral = Referral::query()->where('referred_user_id', $member->id)->first();
            if ($referral === null || ! self::hasStartedTrial($member)) {
                return;
            }

            if ($referral->trial_started_at === null) {
                $referral->forceFill(['trial_started_at' => now()])->save();
            }

            self::settleTrial($referral);
        } catch (Throwable $exception) {
            Log::error('Referral trial reward could not be booked.', ['user_id' => $member->id, 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
        }
    }

    /** A phone was just verified by a real SMS code: the rewards that waited for exactly that are paid now. */
    public static function phoneVerified(array $userIds): void
    {
        foreach ($userIds as $id) {
            $user = User::query()->find($id);
            if ($user !== null) {
                self::trialStarted($user);
                $referral = Referral::query()->where('referred_user_id', $user->id)->first();
                if ($referral !== null) {
                    self::settleOnClaim($referral);
                }
            }
        }
    }

    /** Pays the referrer's reward for an ACCEPTED code (once; waits for a verified phone). Never throws. */
    private static function settleOnClaim(Referral $referral, bool $adminVouched = false): void
    {
        try {
            if ($referral->trial_started_at === null) {
                $referral->forceFill(['trial_started_at' => now()])->save();
            }
            self::settleTrial($referral, $adminVouched);
        } catch (Throwable $exception) {
            Log::error('Referral reward could not be booked.', ['referral_id' => $referral->id, 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
        }
    }

    /**
     * The trial reward, once per referral. Due only when the trial started AND the new member's phone was verified by a code that really
     * travelled by SMS (a fixed/admin code proves nothing about the number — fake accounts would farm it). Past the referrer's daily limit
     * the reward is booked as 0 so the member stays referred and keeps the discount.
     */
    private static function settleTrial(Referral $referral, bool $adminVouched = false): void
    {
        if ($referral->trial_rewarded_at !== null || $referral->trial_started_at === null) {
            return;
        }

        DB::transaction(function () use ($referral, $adminVouched) {
            $referral = Referral::query()->lockForUpdate()->find($referral->id);
            $referred = User::query()->find($referral->referred_user_id);
            $referrer = User::query()->find($referral->referrer_user_id);

            if ($referral->trial_rewarded_at !== null || $referred === null || $referrer === null || ($referred->phone_verified_at === null && ! $adminVouched)) {
                return;
            }

            /* Serialise one referrer's payouts so the daily count below cannot be raced past. */
            User::query()->whereKey($referrer->id)->lockForUpdate()->first();
            $today = ReferralReward::query()->where('referrer_user_id', $referrer->id)->where('kind', ReferralReward::KIND_TRIAL)
                ->where('amount', '>', 0)->where('created_at', '>=', now()->subDay())->count();
            $amount = $today >= self::dailyLimit() ? 0.0 : self::signupBonus();

            self::book($referral, ReferralReward::KIND_TRIAL, 'trial:'.$referral->id, $amount, ['note' => $amount > 0 ? 'Code accepted' : 'Code accepted (daily limit reached)']);
            $referral->forceFill(['trial_rewarded_at' => now(), 'trial_bonus' => $amount])->save();
        });
    }

    /** `year` | `half` | `month` | `other` — what a paid subscription of `$months` months was. */
    public static function planOf(int $months): string
    {
        return match (true) {
            $months === 1 => 'month',
            $months === MembershipPlan::months() => 'year',
            $months === MembershipPlan::halfMonths() => 'half',
            default => 'other',
        };
    }

    /**
     * Called once a subscription was ACCEPTED (`ActivateSubscription`, any method — Paymob, receipt, cash, wallet; first payment AND
     * every renewal): the referrer earns the commission on what the member REALLY paid (`amount`, already after any discount).
     * Idempotent — the reward key and the wallet key are both the subscription id. Never undoes the activation.
     */
    public static function subscriptionPaid(Membership $membership, MembershipSubscription $subscription): void
    {
        try {
            DB::transaction(function () use ($membership, $subscription) {
                $referral = Referral::query()->where('referred_user_id', $membership->user_id)->lockForUpdate()->first();
                if ($referral === null || $subscription->status !== MembershipSubscription::STATUS_ACCEPTED) {
                    return;
                }

                $plan = self::planOf((int) $subscription->months);

                /* The first accepted payment spends the new member's discount (the one of the plan they bought). */
                if ($referral->subscribed_at === null) {
                    $referral->forceFill(['subscription_id' => $subscription->id, 'subscribed_at' => now(), 'discount_amount' => self::discountFor($plan)])->save();
                }

                if (! self::enabled()) {
                    return;
                }

                $paid = round((float) $subscription->amount, 2);
                $commission = self::commissionFor($plan, $paid);

                self::book($referral, ReferralReward::KIND_COMMISSION, 'commission:'.$subscription->id, $commission['amount'], [
                    'subscription_id' => $subscription->id, 'plan' => $plan,
                    'base_amount' => $paid, 'rate' => $commission['rate'],
                    'note' => 'Subscription #'.$subscription->id.' ('.$subscription->method.($commission['type'] === 'fixed' ? ', fixed' : '').')',
                ]);
            });
        } catch (Throwable $exception) {
            /* The member's card is already extended; a failed commission must never undo that. */
            Log::error('Referral commission could not be booked.', [
                'membership_id' => $membership->id, 'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * A paid subscription was refunded / voided: the commission it earned is taken back with a reversal debit (once). The wallet NEVER
     * goes below zero: only what is still in the wallet is taken back; the part already spent is recorded on the reward (`note`).
     */
    public static function subscriptionRefunded(MembershipSubscription $subscription, string $reason = 'refunded'): void
    {
        try {
            DB::transaction(function () use ($subscription, $reason) {
                $reward = ReferralReward::query()->where('reward_key', 'commission:'.$subscription->id)->lockForUpdate()->first();
                if ($reward === null || $reward->status === ReferralReward::STATUS_REVERSED) {
                    return;
                }

                $note = null;
                if ((float) $reward->amount > 0) {
                    /* Serialise with the member's other wallet movements, then never take more than the wallet holds. */
                    User::query()->whereKey($reward->referrer_user_id)->lockForUpdate()->first();
                    $take = round(min((float) $reward->amount, max(0.0, WalletTransaction::balanceOf((int) $reward->referrer_user_id))), 2);

                    if ($take > 0) {
                        WalletTransaction::query()->firstOrCreate(
                            ['user_id' => $reward->referrer_user_id, 'client_id' => 'referral-reversal:'.$reward->reward_key],
                            ['source' => WalletTransaction::SOURCE_REFERRAL, 'amount' => -$take, 'note' => 'referral commission reversed (subscription #'.$subscription->id.')'],
                        );
                    }
                    if ($take < (float) $reward->amount) {
                        $note = 'Not recovered (already spent): '.round((float) $reward->amount - $take, 2);
                    }
                }

                $reward->forceFill(['status' => ReferralReward::STATUS_REVERSED, 'reversed_at' => now(), 'reversal_reason' => $reason, 'note' => $note ?? $reward->note])->save();
            });
        } catch (Throwable $exception) {
            Log::error('Referral commission could not be reversed.', ['subscription_id' => $subscription->id, 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
        }
    }

    /** One payout: the ledger row and its wallet credit, both idempotent on the key. Call inside a transaction. */
    private static function book(Referral $referral, string $kind, string $key, float $amount, array $extra = []): ReferralReward
    {
        $reward = ReferralReward::query()->firstOrCreate(['reward_key' => $key], [
            'referral_id' => $referral->id, 'referrer_user_id' => $referral->referrer_user_id, 'referred_user_id' => $referral->referred_user_id,
            'kind' => $kind, 'amount' => $amount, 'status' => ReferralReward::STATUS_PAID,
        ] + $extra);

        if ($reward->wasRecentlyCreated && $amount > 0) {
            WalletTransaction::query()->firstOrCreate(
                ['user_id' => $referral->referrer_user_id, 'client_id' => 'referral-'.$key],
                ['source' => WalletTransaction::SOURCE_REFERRAL, 'amount' => $amount, 'note' => 'referral '.$kind.' #'.$referral->referred_user_id],
            );
        }

        return $reward;
    }

    /**
     * The referrer's dashboard: totals, wallet, pending money, and one row per member brought with their stage, what they earned for
     * the referrer and every payout. Pending = the trial rewards waiting for a verified phone + the commission on the brought members'
     * subscriptions that are still waiting to be confirmed.
     */
    public static function dashboard(User $user, ?string $code): array
    {
        $referrals = Referral::query()->where('referrer_user_id', $user->id)->with('referred:id,name,phone,phone_verified_at')->latest('id')->limit(500)->get();
        $rewards = ReferralReward::query()->where('referrer_user_id', $user->id)->with('subscription:id,method,months,status')->orderByDesc('id')->limit(2000)->get()->groupBy('referral_id');
        $payments = MembershipSubscription::query()->where('status', MembershipSubscription::STATUS_ACCEPTED)
            ->whereIn('membership_id', Membership::withTrashed()->whereIn('user_id', $referrals->pluck('referred_user_id'))->select('id'))
            ->with('membership:id,user_id')->get()->groupBy(fn ($sub) => $sub->membership?->user_id);
        $pendingSubs = MembershipSubscription::query()->where('status', MembershipSubscription::STATUS_PENDING)->where('amount', '>', 0)
            ->whereIn('membership_id', Membership::withTrashed()->whereIn('user_id', $referrals->pluck('referred_user_id'))->select('id'))->get();

        $waitingTrial = $referrals->filter(fn (Referral $r) => $r->trial_started_at !== null && $r->trial_rewarded_at === null);
        $pendingCommission = $pendingSubs->sum(fn ($sub) => self::commissionFor(self::planOf((int) $sub->months), (float) $sub->amount)['amount']);
        $pending = round($waitingTrial->count() * self::signupBonus() + $pendingCommission, 2);

        $paid = fn ($list, string $kind) => round((float) collect($list)->where('kind', $kind)->where('status', ReferralReward::STATUS_PAID)->sum('amount'), 2);
        $all = $rewards->flatten(1);
        $trialTotal = $paid($all, ReferralReward::KIND_TRIAL);
        $commissionTotal = $paid($all, ReferralReward::KIND_COMMISSION);

        $clicks = $code ? ReferralClick::query()->where('code', $code) : null;

        $rows = $referrals->map(function (Referral $r) use ($rewards, $payments, $paid) {
            $mine = $rewards->get($r->id, collect());
            $count = $payments->get($r->referred_user_id, collect())->count();
            $first = $payments->get($r->referred_user_id, collect())->sortBy('id')->first();
            $stage = match (true) {
                $count >= 2 => 'renewed',
                $count === 1 => 'subscribed',
                $r->trial_started_at !== null => 'trial',
                default => 'registered',
            };

            return [
                'id' => $r->id,
                'name' => $r->referred?->name,
                'phone_last4' => $r->referred?->phone ? substr((string) $r->referred->phone, -4) : null,
                'source' => $r->source,
                'joined_at' => $r->created_at?->toIso8601String(),
                'stage' => $stage,
                'trial_started_at' => $r->trial_started_at?->toIso8601String(),
                'plan' => $first ? self::planOf((int) $first->months) : null,
                'payments' => $count,
                'subscribed' => $count > 0,
                'earned' => round($paid($mine, ReferralReward::KIND_TRIAL) + $paid($mine, ReferralReward::KIND_COMMISSION), 2),
                'rewards' => $mine->map(fn (ReferralReward $w) => [
                    'id' => $w->id, 'kind' => $w->kind, 'amount' => (float) $w->amount, 'status' => $w->status,
                    'base_amount' => (float) $w->base_amount, 'rate' => (float) $w->rate, 'plan' => $w->plan,
                    'at' => $w->created_at?->toIso8601String(), 'reversed_at' => $w->reversed_at?->toIso8601String(),
                ])->values()->all(),
            ];
        })->all();

        return [
            'totals' => [
                'clicks' => $clicks ? (clone $clicks)->count() : 0,
                'app_opens' => $clicks ? (clone $clicks)->whereNotNull('matched_at')->count() : 0,
                'registered' => $referrals->count(),
                'trials' => $referrals->whereNotNull('trial_started_at')->count(),
                'subscribed' => collect($rows)->where('subscribed', true)->count(),
                'trial_earned' => $trialTotal,
                'commission_earned' => $commissionTotal,
                'earned' => round($trialTotal + $commissionTotal, 2),
                'pending' => $pending,
                'wallet_balance' => WalletTransaction::balanceOf($user->id),
            ],
            'rows' => $rows,
        ];
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::ENABLED, 'name' => ['en' => 'Referral programme on', 'ar' => 'تشغيل نظام الدعوات'], 'value' => '1', 'value_type' => Setting::TYPE_BOOLEAN],
            ['slug' => self::SIGNUP_BONUS, 'name' => ['en' => 'Referral: wallet pounds for the member who brought someone (when the new member starts a trial)', 'ar' => 'الدعوات: جنيهات المحفظة لمن جاب عضواً جديداً (عند بدء العضو للتجربة)'], 'value' => '10', 'value_type' => Setting::TYPE_NUMBER],
            ...self::planSeedRows(),
            ['slug' => self::DAILY_LIMIT, 'name' => ['en' => 'Referral: trial rewards per member per day that still pay', 'ar' => 'الدعوات: عدد مكافآت التجربة اليومية لكل عضو'], 'value' => '10', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::WINDOW_DAYS, 'name' => ['en' => 'Referral: days after sign-up a code may still be entered', 'ar' => 'الدعوات: عدد الأيام بعد التسجيل التي يمكن فيها إدخال الكود'], 'value' => '30', 'value_type' => Setting::TYPE_NUMBER],
        ];
    }

    /** The per-plan settings: discount, fixed-or-percent switch, percent, fixed sum — for month, half and year. */
    public static function planSeedRows(): array
    {
        $names = ['month' => ['Monthly', 'الشهري'], 'half' => ['Half-year', 'النصف سنوي'], 'year' => ['Yearly', 'السنوي']];
        $discounts = ['month' => '25', 'half' => '50', 'year' => '100'];
        $rows = [];

        foreach (self::PLANS as $plan) {
            [$en, $ar] = $names[$plan];
            $rows[] = ['slug' => self::DISCOUNT.'_'.$plan, 'name' => ['en' => "Referral: discount (EGP) on the new member's first subscription — $en plan", 'ar' => "الدعوات: خصم (ج.م) على أول اشتراك للعضو الجديد — الباقة $ar"], 'value' => $discounts[$plan], 'value_type' => Setting::TYPE_NUMBER];
            $rows[] = ['slug' => 'referral_commission_use_fixed_'.$plan, 'name' => ['en' => "Referral: $en plan commission is a FIXED sum (off = a percent)", 'ar' => "الدعوات: عمولة الباقة $ar مبلغ ثابت (مغلق = نسبة مئوية)"], 'value' => '0', 'value_type' => Setting::TYPE_BOOLEAN];
            $rows[] = ['slug' => self::COMMISSION_PERCENT.'_'.$plan, 'name' => ['en' => "Referral: commission percent — $en plan", 'ar' => "الدعوات: نسبة العمولة — الباقة $ar"], 'value' => '10', 'value_type' => Setting::TYPE_NUMBER];
            $rows[] = ['slug' => 'referral_commission_fixed_'.$plan, 'name' => ['en' => "Referral: commission fixed sum (EGP) — $en plan", 'ar' => "الدعوات: العمولة كمبلغ ثابت (ج.م) — الباقة $ar"], 'value' => '0', 'value_type' => Setting::TYPE_NUMBER];
        }

        return $rows;
    }
}
