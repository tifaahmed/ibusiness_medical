<?php

use App\Models\FamilyMember;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A relative is a member: a `users` row + a `memberships` row, like the card
 * owner, marked `member_type = relative` and tied to the primary card by
 * `parent_membership_id`. This migration only ADDS — the columns, then a COPY
 * of every `family_members` row (soft-deleted ones too). The old table stays
 * untouched; a later release drops it once the copy is confirmed in production.
 *
 * Re-runnable: a relative already copied (`legacy_family_member_id`) is skipped.
 *
 * Phone / e-mail / National ID must belong to ONE live record. When a relative's
 * value is already held by someone else (or by an earlier relative) the value is
 * NOT copied; it is kept in `legacy_notes` on the relative so nothing is lost,
 * and the closest primary member keeps it. Rows are written through Eloquent so
 * the identity guards run.
 */
return new class extends Migration
{
    private const FEMALE = ['wife', 'daughter', 'mother', 'sister'];

    public function up(): void
    {
        if (! Schema::hasColumn('memberships', 'member_type')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->string('member_type', 20)->default('primary')->index();
                $table->foreignId('parent_membership_id')->nullable()->index()->constrained('memberships')->cascadeOnDelete();
                $table->string('relationship', 20)->nullable();
                $table->string('approval_status', 20)->default('approved')->index();
                $table->string('added_by', 20)->default('admin');
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->string('rejection_reason')->nullable();
                $table->unsignedBigInteger('legacy_family_member_id')->nullable()->unique();
                $table->json('legacy_notes')->nullable();
            });
        }

        if (! Schema::hasTable('family_members')) {
            return;
        }

        $digits = fn (?string $v) => preg_replace('/\D+/', '', (string) $v);
        $takenPhones = User::query()->whereNotNull('phone')->pluck('phone')->map($digits)->filter()->flip()->all();
        $takenEmails = User::query()->whereNotNull('email')->pluck('email')->map(fn ($e) => Str::lower($e))->flip()->all();
        $takenIds = Membership::query()->whereNotNull('national_id')->pluck('national_id')->flip()->all();

        // One unusable password, hashed once (bcrypt per row made 2,000 relatives take an hour). Nobody knows the string it hides.
        $unusable = Hash::make(Str::random(40));

        $copied = 0;

        FamilyMember::withTrashed()->orderBy('id')->each(function (FamilyMember $old) use ($unusable, &$takenPhones, &$takenEmails, &$takenIds, $digits, &$copied) {
            if (Membership::withTrashed()->where('legacy_family_member_id', $old->id)->exists()) {
                $copied++;

                return;
            }

            $notes = [];
            $phone = $old->phone ? trim($old->phone) : null;
            $email = $old->email ? trim($old->email) : null;
            $nid = $old->national_id ? trim($old->national_id) : null;

            if ($phone !== null && isset($takenPhones[$digits($phone)])) {
                $notes['phone_removed_duplicate'] = $phone;
                $phone = null;
            }
            if ($email !== null && isset($takenEmails[Str::lower($email)])) {
                $notes['email_removed_duplicate'] = $email;
                $email = null;
            }
            if ($nid !== null && (isset($takenIds[$nid]) || strlen($nid) > 14)) {
                $notes['national_id_removed'] = $nid;
                $nid = null;
            }

            DB::transaction(function () use ($old, $phone, $email, $nid, $notes, $unusable, &$takenPhones, &$takenEmails, &$takenIds, $digits, &$copied) {
                $user = new User;
                $user->forceFill([
                    'name' => $old->name,
                    'phone' => $phone,
                    'email' => $email,
                    'date_of_birth' => $old->date_of_birth,
                    'gender' => in_array($old->getRawOriginal('relationship'), self::FEMALE, true) ? 'female' : 'male',
                    'password' => $unusable,
                    'partner_id' => null,
                ])->save();

                $number = Membership::newNumber();

                $relative = new Membership;
                $relative->forceFill([
                    'user_id' => $user->id,
                    'membership_number' => $number,
                    'slug' => $number,
                    'national_id' => $nid,
                    'member_type' => 'relative',
                    'parent_membership_id' => $old->membership_id,
                    'relationship' => $old->getRawOriginal('relationship'),
                    'approval_status' => $old->getRawOriginal('approval_status') ?: 'approved',
                    'added_by' => $old->added_by ?: 'admin',
                    'approved_at' => $old->approved_at,
                    'approved_by' => $old->approved_by,
                    'rejection_reason' => $old->rejection_reason,
                    'legacy_family_member_id' => $old->id,
                    'legacy_notes' => $notes ?: null,
                    'is_active' => (bool) $old->is_active,
                    'is_visible' => true,
                    'is_paid' => false,
                    'completed_at' => $old->created_at ?? now(),
                    'created_at' => $old->created_at,
                    'updated_at' => $old->updated_at,
                    'deleted_at' => $old->deleted_at,
                ])->save();

                // Files follow the person: same media row, new owner (the path is the media id, so nothing is copied or lost).
                DB::table('media')
                    ->where('model_type', $old->getMorphClass())
                    ->where('model_id', $old->id)
                    ->update(['model_type' => $relative->getMorphClass(), 'model_id' => $relative->id]);

                if ($notes) {
                    MemberLog::record(
                        membershipId: $relative->id,
                        adminId: null,
                        action: MemberLog::ACTION_UPDATED,
                        oldValues: null,
                        newValues: ['moved_from_family_members' => $old->id] + $notes,
                        request: null,
                    );
                }

                if ($old->deleted_at === null) {
                    if ($phone !== null) {
                        $takenPhones[$digits($phone)] = true;
                    }
                    if ($email !== null) {
                        $takenEmails[Str::lower($email)] = true;
                    }
                    if ($nid !== null) {
                        $takenIds[$nid] = true;
                    }
                }

                $copied++;
            });
        });

        $expected = FamilyMember::withTrashed()->count();

        if ($copied !== $expected || Membership::withTrashed()->whereNotNull('legacy_family_member_id')->count() !== $expected) {
            throw new RuntimeException("Relatives copy mismatch: {$expected} in family_members, {$copied} copied. Nothing was dropped; fix and run again.");
        }
    }

    public function down(): void
    {
        // Deliberately empty: this migration only adds, and rolling back must never delete members.
    }
};
