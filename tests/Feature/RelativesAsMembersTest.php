<?php

namespace Tests\Feature;

use App\Actions\Membership\CreateRelative;
use App\Actions\Membership\UpdateRelative;
use App\Models\FamilyMember;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Relatives are rows of `memberships` (type `relative`) with their own user —
 * not a separate table. These pin the rules the owner asked for and the copy
 * of the old `family_members` rows.
 */
class RelativesAsMembersTest extends TestCase
{
    use RefreshDatabase;

    private function primary(string $name = 'Ahmed Ali Hassan', string $phone = '01012345678'): Membership
    {
        $user = User::factory()->create(['name' => $name, 'phone' => $phone, 'gender' => 'male']);
        $number = Membership::newNumber();

        return Membership::query()->create([
            'user_id' => $user->id, 'membership_number' => $number, 'slug' => $number,
            'registration_date' => now(), 'expiration_date' => now()->addYear(), 'plan' => 'full',
            'is_active' => true, 'is_visible' => true, 'completed_at' => now(), 'governorate_id' => null,
        ]);
    }

    private function add(Membership $primary, array $data = []): Membership
    {
        return app(CreateRelative::class)->handle($primary, $data + [
            'name' => 'Mona Ahmed', 'relationship' => 'wife', 'date_of_birth' => '1990-01-01',
        ]);
    }

    public function test_a_relative_is_a_member_row_with_a_six_digit_number_and_no_plan_of_its_own(): void
    {
        $primary = $this->primary();
        $relative = $this->add($primary);

        $this->assertSame('relative', $relative->member_type);
        $this->assertSame($primary->id, $relative->parent_membership_id);
        $this->assertSame('wife', $relative->relationship);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $relative->membership_number);
        $this->assertSame('Mona Ahmed', $relative->user->name);
        $this->assertNull($relative->expiration_date);
        $this->assertSame(Membership::PLAN_RELATIVE, $relative->plan);
        $this->assertSame([$relative->id], $primary->relatives()->pluck('id')->all());
    }

    public function test_a_relative_cannot_belong_to_a_relative(): void
    {
        $relative = $this->add($this->primary());

        $this->expectException(ValidationException::class);
        $this->add($relative, ['name' => 'Third Person', 'relationship' => 'son']);
    }

    public function test_the_model_itself_refuses_a_relative_without_a_relation_or_with_a_relative_parent(): void
    {
        $primary = $this->primary();
        $relative = $this->add($primary);
        $user = User::factory()->create(['date_of_birth' => '2000-01-01']);

        foreach ([
            ['relationship' => null, 'parent_membership_id' => $primary->id],
            ['relationship' => 'son', 'parent_membership_id' => $relative->id],
            ['relationship' => 'son', 'parent_membership_id' => null],
        ] as $bad) {
            try {
                Membership::query()->create($bad + ['user_id' => $user->id, 'membership_number' => Membership::newNumber(), 'slug' => Membership::newNumber(), 'member_type' => 'relative']);
                $this->fail('A malformed relative was saved.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_one_of_phone_birth_date_or_national_id_is_required_and_any_one_is_enough(): void
    {
        $primary = $this->primary();

        try {
            app(CreateRelative::class)->handle($primary, ['name' => 'Mona Ahmed', 'relationship' => 'wife']);
            $this->fail('A relative with none of the three was saved.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('phone', $e->errors());
        }

        $this->assertNotNull(app(CreateRelative::class)->handle($primary, ['name' => 'A One', 'relationship' => 'wife', 'phone' => '01122223333']));
        $this->assertNotNull(app(CreateRelative::class)->handle($primary, ['name' => 'B Two', 'relationship' => 'mother', 'national_id' => '28001010101010']));
        $this->assertNotNull(app(CreateRelative::class)->handle($primary, ['name' => 'C Three', 'relationship' => 'father', 'date_of_birth' => '1950-02-02']));
        $this->assertNotNull(app(CreateRelative::class)->handle($primary, ['name' => 'D Four', 'relationship' => 'son', 'phone' => '01233334444', 'national_id' => '30001010101010', 'date_of_birth' => '2010-01-01']));
    }

    public function test_a_phone_or_national_id_already_held_is_refused(): void
    {
        $primary = $this->primary(phone: '01012345678');

        try {
            $this->add($primary, ['phone' => '01012345678']);
            $this->fail('A repeated phone was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('phone', $e->errors());
        }

        $this->add($primary, ['name' => 'Has Id', 'national_id' => '28001010101010', 'date_of_birth' => null]);

        try {
            $this->add($this->primary('Other Man', '01055556666'), ['name' => 'Same Id', 'national_id' => '28001010101010']);
            $this->fail('A repeated National ID was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('national_id', $e->errors());
        }
    }

    public function test_only_a_son_or_daughter_may_be_typed_with_the_first_name_alone(): void
    {
        $primary = $this->primary('Ahmed Ali Hassan');

        $son = $this->add($primary, ['name' => 'Omar', 'relationship' => 'son']);
        $this->assertSame('Omar Ahmed Ali Hassan', $son->user->name);

        $daughter = $this->add($primary, ['name' => 'Laila', 'relationship' => 'daughter']);
        $this->assertSame('Laila Ahmed Ali Hassan', $daughter->user->name);

        $this->expectException(ValidationException::class);
        $this->add($primary, ['name' => 'Mona', 'relationship' => 'wife']);
    }

    public function test_the_address_and_commercial_links_follow_the_primary(): void
    {
        $primary = $this->primary();
        $primary->forceFill(['partner_id' => null, 'created_by' => null])->save();

        $relative = $this->add($primary);

        $this->assertSame($primary->governorate_id, $relative->governorate_id);
        $this->assertSame($primary->city_id, $relative->city_id);
        $this->assertSame($primary->partner_id, $relative->partner_id);
        $this->assertSame($primary->sales_id, $relative->sales_id);
    }

    public function test_deleting_the_primary_deletes_its_relatives_too(): void
    {
        $primary = $this->primary();
        $one = $this->add($primary);
        $two = $this->add($primary, ['name' => 'Omar', 'relationship' => 'son']);
        $stranger = $this->add($this->primary('Other Man', '01055556666'), ['name' => 'Not Mine', 'relationship' => 'wife']);

        $primary->delete();

        $this->assertSoftDeleted('memberships', ['id' => $one->id]);
        $this->assertSoftDeleted('memberships', ['id' => $two->id]);
        $this->assertNotSoftDeleted('memberships', ['id' => $stranger->id]);
    }

    public function test_a_relative_is_as_valid_as_its_primary_and_signs_in_with_their_own_phone(): void
    {
        $primary = $this->primary();
        $relative = $this->add($primary, ['phone' => '01122223333', 'is_active' => true]);

        $this->assertTrue($relative->isCurrent());
        $this->assertSame(Membership::STATE_ACTIVE, $relative->state());

        $primary->forceFill(['expiration_date' => now()->subDay()])->save();
        $relative->refresh()->unsetRelation('parent');
        $this->assertFalse($relative->isCurrent());
        $this->assertSame(Membership::STATE_EXPIRED, $relative->state());

        $this->assertSame($relative->user_id, app(\App\Services\Otp\MemberOtp::class)->findMember('01122223333')?->id);
    }

    public function test_staff_turning_a_relative_on_approves_it_and_editing_keeps_the_rules(): void
    {
        $primary = $this->primary();
        $relative = app(CreateRelative::class)->handle($primary, ['name' => 'Mona Ahmed', 'relationship' => 'wife', 'phone' => '01122223333'], Membership::ADDED_BY_MEMBER);
        $this->assertSame('pending', $relative->approval_status);
        $this->assertFalse((bool) $relative->is_active);

        $relative->update(['is_active' => true]);
        $this->assertSame('approved', $relative->fresh()->approval_status);

        // Removing the only identifier is refused.
        $this->expectException(ValidationException::class);
        app(UpdateRelative::class)->handle($relative->fresh(), ['phone' => '']);
    }

    public function test_the_old_family_rows_are_copied_without_losing_one_and_a_repeated_identifier_is_moved_to_the_note(): void
    {
        $primary = $this->primary('Ahmed Ali Hassan', '01012345678');
        $other = $this->primary('Khaled Samir', '01055556666');
        $other->forceFill(['national_id' => '28001010101010'])->save();

        $make = fn (array $a) => DB::table('family_members')->insertGetId($a + ['membership_id' => $primary->id, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $clash = $make(['name' => 'Clash Wife', 'relationship' => 'wife', 'phone' => '01012345678', 'national_id' => '28001010101010']);
        $plain = $make(['name' => 'Plain Son', 'relationship' => 'son']);
        $gone = $make(['name' => 'Gone Mother', 'relationship' => 'mother', 'phone' => '01199998888', 'deleted_at' => now()]);

        (require database_path('migrations/2026_10_29_100000_add_relatives_to_memberships.php'))->up();
        // Run twice: a re-run copies nothing new.
        (require database_path('migrations/2026_10_29_100000_add_relatives_to_memberships.php'))->up();

        $this->assertSame(3, Membership::withTrashed()->whereNotNull('legacy_family_member_id')->count());

        $c = Membership::withTrashed()->where('legacy_family_member_id', $clash)->first();
        $this->assertSame('relative', $c->member_type);
        $this->assertSame($primary->id, $c->parent_membership_id);
        $this->assertNull($c->user->phone);
        $this->assertNull($c->national_id);
        $this->assertSame('01012345678', $c->legacy_notes['phone_removed_duplicate']);
        $this->assertSame('28001010101010', $c->legacy_notes['national_id_removed']);
        $this->assertSame('Clash Wife', $c->user->name);

        $this->assertNotNull(Membership::withTrashed()->where('legacy_family_member_id', $plain)->first(), 'a row with none of the three still arrives');
        $this->assertNotNull(Membership::withTrashed()->where('legacy_family_member_id', $gone)->first()->deleted_at, 'a deleted relative stays deleted');
        $this->assertSame(3, DB::table('family_members')->count(), 'the old table is untouched');
    }
}
