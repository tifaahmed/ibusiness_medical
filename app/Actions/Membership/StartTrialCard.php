<?php

namespace App\Actions\Membership;

use App\Models\FamilyMember;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\TrialClaim;
use App\Models\User;
use App\Services\Otp\MemberRegistration;
use App\Support\MembershipPlan;
use App\Support\PhoneNumbers;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Issues a free, self-service trial card to a phone that has just proved it.
 *
 * Needs three things only — the phone (already proven by the sign-in code), a
 * name and a national ID — and returns a working card that expires after the
 * trial period. The card holds ONLY the owner: relatives are added by customer
 * services during the trial, or by the member once they have paid.
 *
 * The phone and the ID may hold one card each. "Taken" means a card that EXISTS
 * holds it — a deleted card (soft or hard) or an inert `REG-` placeholder does
 * not count, so a member whose card was deleted can start a trial again:
 *  - `trial_claims` whose card still exists (DB unique indexes win any race; a
 *    claim whose card is gone is released — logged on the new card, then freed);
 *  - live cards and live relatives carrying the same ID — covers everything
 *    issued before this ledger existed;
 *  - another account with the same phone that holds a live card.
 * Because sign-in is by phone, a second device simply signs into the SAME account;
 * a different phone with the same ID is what gets refused.
 */
class StartTrialCard
{
    /** Why a trial was refused — clients key their wording off this. */
    public const REASON_HAS_CARD = 'already_has_card';

    public const REASON_ID_TAKEN = 'national_id_taken';

    public const REASON_PHONE_TAKEN = 'phone_taken';

    public function handle(User $user, string $name, string $nationalId, ?Request $request = null): Membership
    {
        $nationalId = preg_replace('/\D/', '', $nationalId) ?? '';
        $phone = PhoneNumbers::national((string) $user->phone);

        if ($phone === null) {
            throw $this->refuse('phone', self::REASON_PHONE_TAKEN, 'A valid phone number is needed to issue a card.');
        }

        $existing = $user->memberships()->latest('id')->first();

        if ($existing !== null && ! MemberRegistration::isPending($existing->membership_number)) {
            throw $this->refuse('phone', self::REASON_HAS_CARD, 'You already have a card.');
        }

        $this->assertNotTaken($user, $phone, $nationalId);

        try {
            return DB::transaction(function () use ($user, $name, $nationalId, $phone, $existing, $request) {
                $number = $this->newNumber();
                $now = now();

                $user->forceFill(['name' => trim($name)])->save();

                $attributes = [
                    'membership_number' => $number,
                    'slug' => $number,
                    'national_id' => $nationalId,
                    'registration_date' => $now,
                    'expiration_date' => $now->copy()->addMonthsNoOverflow(MembershipPlan::trialMonths()),
                    'trial_started_at' => $now,
                    'plan' => Membership::PLAN_TRIAL,
                    'source' => Membership::SOURCE_SELF_SERVICE,
                    'is_active' => true,
                    'is_visible' => true,
                    'is_paid' => false,
                    /* The admin list hides rows with no completed_at as "in progress". */
                    'completed_at' => $now,
                ];

                /* A phone sign-up already left an inert placeholder: upgrade that row, never leave two cards. */
                $membership = $existing ?? new Membership(['user_id' => $user->id]);
                $membership->forceFill($attributes)->save();

                $released = $this->releaseStaleClaims($phone, $nationalId);

                TrialClaim::query()->create([
                    'national_id' => $nationalId,
                    'phone' => $phone,
                    'membership_id' => $membership->id,
                    'user_id' => $user->id,
                    'ip_address' => $request?->ip(),
                    'user_agent' => $request ? substr((string) $request->userAgent(), 0, 1000) : null,
                ]);

                MemberLog::record(
                    membershipId: $membership->id,
                    adminId: null,
                    action: MemberLog::ACTION_CREATED,
                    oldValues: null,
                    newValues: [
                        'plan' => Membership::PLAN_TRIAL,
                        'membership_number' => $number,
                        'expiration_date' => $membership->expiration_date->toDateString(),
                        'source' => Membership::SOURCE_SELF_SERVICE,
                    ] + ($released ? ['released_trial_claims' => $released] : []),
                    request: $request,
                );

                return $membership->refresh();
            });
        } catch (QueryException $exception) {
            /* Lost a race on the unique indexes: somebody else claimed the phone or the ID a moment ago. */
            if (str_contains($exception->getMessage(), 'trial_claims')) {
                throw $this->refuse('national_id', self::REASON_ID_TAKEN, 'This ID or phone number already has a card.');
            }

            throw $exception;
        }
    }

    /** Whether somebody else already holds this national ID — the same test the trial itself applies. */
    public function nationalIdTaken(User $user, string $nationalId): bool
    {
        return TrialClaim::query()->holdingCard()->where('national_id', $nationalId)->exists()
            || Membership::query()->where('national_id', $nationalId)->where('user_id', '!=', $user->id)
                ->where('membership_number', 'not like', MemberRegistration::PENDING_PREFIX.'%')->exists()
            || FamilyMember::query()->where('national_id', $nationalId)->exists();
    }

    /**
     * Frees the claims on this phone / ID whose card no longer exists, so the
     * unique indexes let the new claim in. Only stale rows are touched — a claim
     * whose card is alive is never removed — and each freed row is returned to be
     * kept in the new card's log, so the history of the earlier trial survives.
     */
    private function releaseStaleClaims(string $phone, string $nationalId): array
    {
        $stale = TrialClaim::query()->stale()
            ->where(fn ($q) => $q->where('phone', $phone)->orWhere('national_id', $nationalId))
            ->lockForUpdate()
            ->get();

        foreach ($stale as $claim) {
            Log::warning('Stale trial claim released', ['trial_claim' => $claim->toArray()]);
            $claim->delete();
        }

        return $stale->map(fn (TrialClaim $c) => $c->only(['id', 'national_id', 'phone', 'membership_id', 'user_id', 'ip_address', 'created_at']))->all();
    }

    private function assertNotTaken(User $user, string $phone, string $nationalId): void
    {
        if ($this->nationalIdTaken($user, $nationalId)) {
            throw $this->refuse('national_id', self::REASON_ID_TAKEN, 'This ID number already has a card. Please contact customer service.');
        }

        $claimedPhone = TrialClaim::query()->holdingCard()->where('phone', $phone)->exists();

        $otherAccountWithCard = User::query()
            ->where('id', '!=', $user->id)
            ->whereRaw(User::phoneDigitsSql().' IN (?, ?, ?)', [$phone, ltrim($phone, '0'), '20'.ltrim($phone, '0')])
            ->whereHas('memberships', fn ($q) => $q->where('membership_number', 'not like', MemberRegistration::PENDING_PREFIX.'%'))
            ->exists();

        if ($claimedPhone || $otherAccountWithCard) {
            throw $this->refuse('phone', self::REASON_PHONE_TAKEN, 'This phone number already has a card. Please contact customer service.');
        }
    }

    private function refuse(string $field, string $reason, string $message): ValidationException
    {
        $exception = ValidationException::withMessages([$field => [$message]]);
        $exception->errorBag = $reason;

        return $exception;
    }

    /** A card number nobody holds: six digits (the card prints it as 123-456; the dash is never stored). */
    private function newNumber(): string
    {
        do {
            $number = (string) random_int(100000, 999999);
        } while ($this->numberInUse($number));

        return $number;
    }

    private function numberInUse(string $number): bool
    {
        return Membership::withTrashed()->where(fn ($q) => $q->where('membership_number', $number)->orWhere('slug', $number))->exists();
    }
}
