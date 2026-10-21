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
use Illuminate\Validation\ValidationException;

/**
 * Issues a free, self-service trial card to a phone that has just proved it.
 *
 * Needs three things only — the phone (already proven by the sign-in code), a
 * name and a national ID — and returns a working card that expires after the
 * trial period. The card holds ONLY the owner: relatives are added by customer
 * services during the trial, or by the member once they have paid.
 *
 * The phone and the ID are unique across the whole system. That is enforced
 * three ways, because each alone leaves a hole:
 *  - `trial_claims` (DB unique indexes) — survives deleted cards, and wins any race;
 *  - existing cards and relatives carrying the same ID — covers everything issued
 *    before this ledger existed;
 *  - another account with the same phone that already holds a card.
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
                    ],
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
        return TrialClaim::query()->where('national_id', $nationalId)->exists()
            || Membership::withTrashed()->where('national_id', $nationalId)->where('user_id', '!=', $user->id)->exists()
            || FamilyMember::withTrashed()->where('national_id', $nationalId)->exists();
    }

    private function assertNotTaken(User $user, string $phone, string $nationalId): void
    {
        if ($this->nationalIdTaken($user, $nationalId)) {
            throw $this->refuse('national_id', self::REASON_ID_TAKEN, 'This ID number already has a card. Please contact customer service.');
        }

        $claimedPhone = TrialClaim::query()->where('phone', $phone)->exists();

        $otherAccountWithCard = User::query()
            ->where('id', '!=', $user->id)
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') IN (?, ?, ?)", [$phone, ltrim($phone, '0'), '20'.ltrim($phone, '0')])
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

    /** A card number nobody holds, in the same 4-digit series as every other card. */
    private function newNumber(): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $number = (string) random_int(1000, 9999);

            if (! $this->numberInUse($number)) {
                return $number;
            }
        }

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
