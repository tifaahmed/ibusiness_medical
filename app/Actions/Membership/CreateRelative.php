<?php

namespace App\Actions\Membership;

use App\Enums\FamilyMember\ApprovalStatusEnum;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The ONE way a relative comes into being: a `users` row + a `memberships` row
 * (type `relative`) hanging on a PRIMARY card. The admin form, the app, the
 * website and the importers all come through here, so the rules cannot differ:
 * at least one of phone / date of birth / National ID, nobody's phone, e-mail
 * or National ID repeated, the address and the commercial links (partner,
 * company, sales) inherited from the primary, a fresh six-digit number, no
 * subscription of its own.
 */
class CreateRelative
{
    /** An unusable password, hashed once per process: a relative never signs in. */
    private static ?string $unusablePassword = null;

    /**
     * @param  array{name: string, relationship: string, phone?: ?string, date_of_birth?: mixed, national_id?: ?string, email?: ?string, is_active?: bool}  $data
     */
    public function handle(Membership $primary, array $data, string $addedBy = Membership::ADDED_BY_ADMIN, ?int $adminId = null, ?Request $request = null): Membership
    {
        if ($primary->isRelative()) {
            throw ValidationException::withMessages(['relationship' => ['A relative cannot have relatives of their own.']]);
        }

        $phone = $this->clean($data['phone'] ?? null);
        $email = $this->clean($data['email'] ?? null);
        $nationalId = $this->clean($data['national_id'] ?? null);
        $dateOfBirth = $this->clean($data['date_of_birth'] ?? null);

        if ($phone === null && $nationalId === null && $dateOfBirth === null) {
            throw ValidationException::withMessages(['phone' => ['Enter at least a phone, a date of birth or a National ID.']]);
        }

        $name = self::resolveName((string) ($data['name'] ?? ''), (string) ($data['relationship'] ?? ''), (string) $primary->user?->name);
        $data['name'] = $name;

        $this->assertFree($phone, $email, $nationalId);

        $isActive = $addedBy === Membership::ADDED_BY_ADMIN ? (bool) ($data['is_active'] ?? true) : false;

        return DB::transaction(function () use ($primary, $data, $phone, $email, $nationalId, $dateOfBirth, $addedBy, $adminId, $request, $isActive) {
            $relationship = (string) $data['relationship'];

            $user = new User;
            $user->forceFill([
                'name' => preg_replace('/\s+/u', ' ', trim((string) $data['name'])),
                'phone' => $phone,
                'email' => $email,
                'date_of_birth' => $dateOfBirth,
                'gender' => in_array($relationship, ['wife', 'daughter', 'mother', 'sister'], true) ? 'female' : 'male',
                'password' => self::$unusablePassword ??= Hash::make(Str::random(40)),
                'partner_id' => $primary->partner_id,
            ])->save();

            $number = Membership::newNumber();
            $now = now();

            $relative = new Membership;
            $relative->forceFill([
                'user_id' => $user->id,
                'membership_number' => $number,
                'slug' => $number,
                'national_id' => $nationalId,
                'member_type' => Membership::TYPE_RELATIVE,
                'parent_membership_id' => $primary->id,
                'relationship' => $relationship,
                'approval_status' => $isActive ? ApprovalStatusEnum::APPROVED->value : ApprovalStatusEnum::PENDING->value,
                'added_by' => $addedBy,
                'approved_at' => $isActive ? $now : null,
                'approved_by' => $isActive ? $adminId : null,
                // The address and the commercial links follow the primary; an admin edits them by hand if they differ.
                'governorate_id' => $primary->governorate_id,
                'city_id' => $primary->city_id,
                'company_id' => $primary->company_id,
                'partner_id' => $primary->partner_id,
                'sales_id' => $primary->sales_id,
                'created_by' => $adminId ?? $primary->created_by,
                'is_active' => $isActive,
                'is_visible' => true,
                'is_paid' => false,
                'registration_date' => $now,
                'completed_at' => $now,
            ])->save();

            MemberLog::record(
                membershipId: $primary->id,
                adminId: $adminId,
                action: MemberLog::ACTION_FAMILY_CREATED,
                oldValues: null,
                newValues: [
                    'relative_membership_id' => $relative->id,
                    'name' => $user->name,
                    'relationship' => $relationship,
                    'added_by' => $addedBy,
                ],
                request: $request,
            );

            return $relative;
        });
    }

    /**
     * Phone, e-mail and National ID each belong to one live record — the same
     * test the model guards apply, run first so the caller gets a message per field.
     */
    public function assertFree(?string $phone, ?string $email, ?string $nationalId, ?int $ignoreUserId = null, ?int $ignoreMembershipId = null): void
    {
        $errors = [];

        if ($phone !== null && User::query()
            ->when($ignoreUserId, fn ($q) => $q->whereKeyNot($ignoreUserId))
            ->whereRaw(User::phoneDigitsSql().' = ?', [preg_replace('/\D+/', '', $phone)])->exists()) {
            $errors['phone'] = ['This phone number already belongs to another member.'];
        }

        if ($email !== null && User::query()->when($ignoreUserId, fn ($q) => $q->whereKeyNot($ignoreUserId))->where('email', $email)->exists()) {
            $errors['email'] = ['This e-mail already belongs to another member.'];
        }

        if ($nationalId !== null && Membership::query()->when($ignoreMembershipId, fn ($q) => $q->whereKeyNot($ignoreMembershipId))->where('national_id', $nationalId)->exists()) {
            $errors['national_id'] = ['This National ID already belongs to another card.'];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A son or daughter may be typed with the FIRST name only — the primary's
     * name is appended, as the app always did. Every other relation needs the
     * full name (at least two words).
     */
    public static function resolveName(string $name, string $relationship, string $primaryName): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['The name is required.']]);
        }

        $isChild = in_array($relationship, ['son', 'daughter'], true);
        $words = count(explode(' ', $name));

        if ($isChild && $words === 1 && trim($primaryName) !== '') {
            return $name.' '.preg_replace('/\s+/u', ' ', trim($primaryName));
        }

        if (! $isChild && $words < 2) {
            throw ValidationException::withMessages(['name' => ['Enter the full name for this relation (only a son or daughter can be the first name alone).']]);
        }

        return $name;
    }

    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }
}
