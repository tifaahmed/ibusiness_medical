<?php

namespace App\Actions\Membership;

use App\Models\MemberLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * An admin wants a phone number another member already holds. The other person is dealt with FIRST, so the number
 * is free when the admin's own form is saved:
 *
 *  - `change`: their phone becomes a different number the admin types (checked like any phone: 01 + 9 digits, unused);
 *  - `clear`:  they are left with no phone;
 *  - `delete`: only for a person with NO membership at all — they go to the trash (soft delete, nothing erased).
 *
 * Every write is an Eloquent save so the unique-phone guard on `User` still runs, and each one is logged.
 */
class TakeOverPhone
{
    public const CHANGE = 'change';

    public const CLEAR = 'clear';

    public const DELETE = 'delete';

    /** What the admin sees before choosing: who holds the number and whether the person may be deleted. */
    public function describe(User $holder): array
    {
        $cards = $holder->memberships()->get(['id', 'membership_number', 'member_type', 'completed_at']);

        return [
            'user_id' => $holder->id,
            'name' => $holder->name,
            'email' => $holder->email,
            'phone' => $holder->phone,
            'slug' => $holder->slug,
            'phone_verified' => $holder->phone_verified_at !== null,
            'cards' => $cards->map(fn ($c) => [
                'id' => $c->id,
                'membership_number' => $c->membership_number,
                'is_relative' => $c->member_type === 'relative',
            ])->values()->all(),
            // Staff accounts are never touched from a member form.
            'is_staff' => $holder->roles()->exists(),
            'can_delete' => $cards->isEmpty() && ! $holder->roles()->exists(),
        ];
    }

    /**
     * @return array{action: string, old_phone: string, new_phone: ?string, user_id: int}
     */
    public function run(User $holder, string $action, ?string $newPhone, ?int $adminId, ?Request $request = null): array
    {
        $old = (string) $holder->phone;

        if ($holder->roles()->exists()) {
            throw ValidationException::withMessages(['phone' => ['This number belongs to a staff account, so it cannot be taken from here.']]);
        }

        if ($action === self::DELETE && $holder->memberships()->exists()) {
            throw ValidationException::withMessages(['phone' => ['This person holds a membership, so they cannot be deleted from here.']]);
        }

        if ($action === self::CHANGE) {
            $newPhone = trim((string) $newPhone);
            if (! preg_match('/^01\d{9}$/', $newPhone)) {
                throw ValidationException::withMessages(['new_phone' => ['The new phone must be 11 digits starting with 01.']]);
            }
            if ($newPhone === $old) {
                throw ValidationException::withMessages(['new_phone' => ['Type a different phone for them.']]);
            }
        }

        return DB::transaction(function () use ($holder, $action, $newPhone, $old, $adminId, $request) {
            $cardId = $holder->memberships()->value('id');

            MemberLog::record(
                membershipId: $cardId ? (int) $cardId : null,
                adminId: $adminId,
                action: $action === self::DELETE ? MemberLog::ACTION_DELETED : MemberLog::ACTION_UPDATED,
                oldValues: ['reason' => 'phone_taken_over', 'user_id' => $holder->id, 'user_name' => $holder->name, 'phone' => $old],
                newValues: $action === self::DELETE
                    ? null
                    : ['reason' => 'phone_taken_over', 'user_id' => $holder->id, 'user_name' => $holder->name, 'phone' => $action === self::CHANGE ? $newPhone : null],
                request: $request,
            );

            if ($action === self::DELETE) {
                $holder->delete(); // soft
            } else {
                $holder->phone = $action === self::CHANGE ? $newPhone : null;
                $holder->phone_verified_at = null; // the new number was never proved by a code
                $holder->save(); // Eloquent on purpose: the unique-phone guard runs
            }

            Log::warning('Phone taken over from another member.', [
                'admin_id' => $adminId, 'user_id' => $holder->id, 'action' => $action, 'old_phone' => $old,
                'new_phone' => $action === self::CHANGE ? $newPhone : null,
            ]);

            return ['action' => $action, 'old_phone' => $old, 'new_phone' => $action === self::CHANGE ? $newPhone : null, 'user_id' => $holder->id];
        });
    }
}
