<?php

namespace App\Services\Otp;

use App\Models\Membership;
use App\Models\User;
use App\Support\PhoneNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sign somebody up from nothing but a phone number they proved they hold.
 *
 * The storefront's login does not refuse an unknown number: it sends a code to
 * it like any other, and a correct code is taken as proof the person on the end
 * of that phone is real. That is the whole of registration — no password, no
 * form, no email to confirm — and everything else is asked for afterwards, when
 * they are already signed in and can be shown how far along they are.
 *
 * What is created is deliberately INERT: an inactive, invisible membership
 * carrying a pending number. It buys nothing — no member price at the checkout,
 * no card, no listing — because a card is issued by staff who have spoken to
 * the person, not by somebody typing a phone number into a website. Customer
 * services picks the registration up from there, which is what the storefront
 * tells them to expect.
 *
 * @see RegistrationProgress for how "how far along" is worked out
 */
class MemberRegistration
{
    /**
     * The prefix on a membership number nobody has issued yet.
     *
     * A real card number is minted by an admin. Registrations need SOMETHING in
     * the column — it is unique and not-null — and a marked, obviously
     * provisional value is better than borrowing from the range real cards are
     * drawn from, where it would eventually collide with one.
     */
    public const PENDING_PREFIX = 'REG-';

    /**
     * Whether a membership number is a placeholder rather than a card.
     */
    public static function isPending(?string $membershipNumber): bool
    {
        return is_string($membershipNumber)
            && str_starts_with($membershipNumber, self::PENDING_PREFIX);
    }

    /**
     * Whether `$phone` belongs to a member of STAFF.
     *
     * Staff hold roles and hold no membership, so `MemberOtp::findMember()`
     * never resolves one — which is what keeps them out of the storefront
     * login. That leaves them looking like an unknown number, and an unknown
     * number is now a registration, so this is the check that stops a code sent
     * to an administrator's phone from quietly creating a second account
     * carrying their number.
     *
     * Deliberately not the same thing as refusing them a code: the code is sent
     * either way, and it is verifying one that this closes off. Nothing about
     * the answer tells the caller whose phone this is.
     */
    public function belongsToStaff(string $phone): bool
    {
        $existing = $this->existingUser($phone);

        return $existing instanceof User && $existing->roles()->exists();
    }

    /**
     * Create — or adopt — a member for `$phone`.
     *
     * One transaction: a user with no membership behind it would be an account
     * the phone login cannot find again, which is worse than no account.
     *
     * An existing user with this number and NO roles is adopted rather than
     * duplicated: `users.phone` is not unique, and a second row carrying the
     * same number would leave the login resolving whichever one it happened to
     * find first. That is a real shape here — a member imported from a
     * spreadsheet whose membership was later removed.
     */
    public function register(string $phone): User
    {
        $national = PhoneNumbers::national($phone) ?? $phone;

        $existing = $this->existingUser($phone);

        if ($existing instanceof User) {
            return DB::transaction(function () use ($existing) {
                $this->attachPendingMembership($existing);

                return $existing->refresh();
            });
        }

        return DB::transaction(function () use ($national) {
            $user = new User;
            $user->phone = $national;
            /*
             * A password is required by the column and useless here: nothing in
             * this flow ever checks one, and a member signs in with a code sent
             * to their phone. A long random value means the account cannot be
             * opened with a guessed password either — there is no password to
             * guess at, only one nobody has ever seen.
             */
            $user->password = Str::random(64);
            /*
             * The slug is minted from the name and frozen on create, and a
             * registration has no name yet. Seeding it from the number keeps
             * the not-null unique column satisfied without inventing a name the
             * member never gave — and `doNotGenerateSlugsOnUpdate()` means
             * filling the name in later leaves this alone, which is exactly
             * what a frozen public key should do.
             */
            $user->slug = 'member-'.$national.'-'.Str::lower(Str::random(6));
            $user->save();

            $this->attachPendingMembership($user);

            return $user->refresh();
        });
    }

    /**
     * The user already holding this number, whoever they are.
     *
     * Matched on the digits of both sides rather than the raw strings, the same
     * way `MemberOtp::findMember()` does — a number stored with a country code
     * or a space is the same number.
     */
    private function existingUser(string $phone): ?User
    {
        $national = PhoneNumbers::national($phone);

        if ($national === null) {
            return null;
        }

        return User::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '') IN (?, ?, ?)",
                [$national, ltrim($national, '0'), '20'.ltrim($national, '0')]
            )
            ->first();
    }

    /**
     * Give a user the inert membership a self-registration gets.
     */
    private function attachPendingMembership(User $user): Membership
    {
        return Membership::query()->create([
            'user_id' => $user->id,
            'membership_number' => $this->pendingNumber(),
            'slug' => 'pending-'.Str::lower(Str::random(12)),
            'registration_date' => now(),
            /*
             * Inactive AND invisible. Together those two are what keep a
             * self-registration out of everything a card buys: the member price
             * at the checkout reads `Membership::earnsMemberPrice()`, and every
             * public lookup goes through the `visible()` scope.
             */
            'is_active' => false,
            'is_visible' => false,
        ]);
    }

    /**
     * A membership number no card will ever be issued with.
     */
    private function pendingNumber(): string
    {
        do {
            $number = self::PENDING_PREFIX.Str::upper(Str::random(10));
        } while (Membership::query()->where('membership_number', $number)->exists());

        return $number;
    }
}
