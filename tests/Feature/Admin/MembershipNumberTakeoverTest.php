<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Taking a membership number another card holds: confirmations, soft delete, number freed, batches follow the number. */
class MembershipNumberTakeoverTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach (['manage memberships', 'manage own memberships', 'manage partner memberships'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $u;
    }

    private function card(string $number): Membership
    {
        return Membership::factory()->create(['user_id' => User::factory()->create()->id, 'membership_number' => $number, 'slug' => $number, 'is_active' => true]);
    }

    public function test_check_describes_what_deleting_would_take(): void
    {
        $holder = $this->card('111111');
        $batch = MembershipCard::create(['batch_name' => 'Spring', 'quantity' => 1, 'start_number' => 1, 'membership_ids' => [$holder->id]]);

        $res = $this->actingAs($this->admin())->withoutMiddleware()->getJson('/api/membership-number/check?membership_number=111111')->assertOk();
        $res->assertJsonPath('unique', false)->assertJsonPath('member.takeover.batches.0.name', 'Spring')->assertJsonPath('member.takeover.membership_id', $holder->id);
    }

    public function test_release_needs_two_confirmations_then_frees_the_number_without_hard_deleting(): void
    {
        $admin = $this->admin();
        $holder = $this->card('222222');

        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '222222', 'confirmations' => 1])->assertStatus(422);
        $this->assertNotNull(Membership::find($holder->id));

        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '222222', 'confirmations' => 2])
            ->assertOk()->assertJsonPath('released', true)->assertJsonPath('released_membership_id', $holder->id);

        $this->assertNull(Membership::find($holder->id));                                   // gone from every list
        $gone = Membership::withTrashed()->find($holder->id);                                // but still there, restorable
        $this->assertSame('222222~del'.$holder->id, $gone->membership_number);
        $this->assertSame('222222~del'.$holder->id, $gone->slug);

        // The number is genuinely free: a new card can take it (both UNIQUE columns).
        $new = $this->card('222222');
        $this->assertSame('222222', $new->membership_number);
        $this->assertDatabaseHas('member_logs', ['membership_id' => $holder->id, 'action' => 'deleted']);
    }

    public function test_batches_follow_the_number_to_the_new_holder_and_never_list_both(): void
    {
        $admin = $this->admin();
        $old = $this->card('333333');
        $other = $this->card('444444');
        $batch = MembershipCard::create(['batch_name' => 'B1', 'quantity' => 2, 'start_number' => 1, 'membership_ids' => [$old->id, $other->id]]);

        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '333333', 'confirmations' => 2])->assertOk();
        $target = $this->card('555555');
        $target->update(['membership_number' => '333333']);

        app(\App\Actions\Membership\TakeOverMembershipNumber::class)->relink($old->id, $target);
        app(\App\Actions\Membership\TakeOverMembershipNumber::class)->relink($old->id, $target); // idempotent

        $this->assertEqualsCanonicalizing([$target->id, $other->id], $batch->fresh()->membership_ids);
        $this->assertNotContains($old->id, $batch->fresh()->membership_ids);
    }

    public function test_a_primary_takes_its_relatives_with_it(): void
    {
        $admin = $this->admin();
        $holder = $this->card('666666');
        $rel = app(\App\Actions\Membership\CreateRelative::class)->handle($holder, ['name' => 'Mona Hassan Ali', 'relationship' => 'wife'], Membership::ADDED_BY_ADMIN);

        $this->actingAs($admin)->withoutMiddleware()->getJson('/api/membership-number/check?membership_number=666666')->assertJsonPath('member.takeover.relatives_count', 1);
        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '666666', 'confirmations' => 2])->assertOk()->assertJsonPath('relatives_deleted', 1);

        $this->assertNull(Membership::find($rel->id));
        $this->assertNotNull(Membership::withTrashed()->find($rel->id));
    }

    public function test_confirmation_is_enforced_by_the_backend_not_the_form(): void
    {
        $holder = $this->card('777777');
        foreach ([[], ['confirmations' => 0], ['confirmations' => 1], ['confirmations' => 'two']] as $extra) {
            $this->actingAs($this->admin())->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '777777', ...$extra])->assertStatus(422);
        }
        $this->assertSame('777777', Membership::find($holder->id)->membership_number);
    }

    public function test_a_failure_in_the_middle_leaves_everything_untouched(): void
    {
        $holder = $this->card('888888');
        $rel = app(\App\Actions\Membership\CreateRelative::class)->handle($holder, ['name' => 'Mona Hassan Ali', 'relationship' => 'wife'], Membership::ADDED_BY_ADMIN);
        Membership::deleted(fn () => throw new \RuntimeException('boom'));

        $this->actingAs($this->admin())->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '888888', 'confirmations' => 2])->assertStatus(500)->assertJsonPath('released', false);

        $this->assertSame('888888', Membership::find($holder->id)->membership_number);   // not renamed, not deleted
        $this->assertNotNull(Membership::find($rel->id));                                // relative still there
        $this->assertDatabaseMissing('member_logs', ['membership_id' => $holder->id, 'action' => 'deleted']); // log rolled back too
        Membership::clearBootedModels();
    }

    public function test_restoring_gives_the_original_number_back_unless_somebody_took_it(): void
    {
        $admin = $this->admin();
        $a = $this->card('121212');
        $b = $this->card('343434');
        foreach (['121212', '343434'] as $n) {
            $this->actingAs($admin)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => $n, 'confirmations' => 2])->assertOk();
        }
        $new = $this->card('343434'); // somebody took b's number; a's stays free
        $action = app(\App\Actions\Membership\TakeOverMembershipNumber::class);

        $ra = Membership::withTrashed()->find($a->id);
        $rb = Membership::withTrashed()->find($b->id);
        $this->assertTrue($action->reinstateNumber($ra));
        $this->assertFalse($action->reinstateNumber($rb));

        $this->assertSame('121212', Membership::withTrashed()->find($a->id)->membership_number);
        $this->assertSame('121212', Membership::withTrashed()->find($a->id)->slug);
        $this->assertSame('343434~del'.$b->id, Membership::withTrashed()->find($b->id)->membership_number); // kept, no collision
        $this->assertSame('343434', $new->fresh()->membership_number);
        $this->assertNull($action->reinstateNumber(Membership::withTrashed()->find($new->id)) ?: null);      // a normal card is never touched
    }

    public function test_search_and_lookups_use_the_current_number_not_the_released_one(): void
    {
        $old = $this->card('565656');
        $this->actingAs($this->admin())->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '565656', 'confirmations' => 2])->assertOk();
        $new = $this->card('565656');

        $this->assertSame([$new->id], Membership::query()->matchingNumber('565656')->pluck('id')->all());
        $this->assertSame([$new->id], Membership::query()->where('membership_number', '565656')->pluck('id')->all());
        $this->assertSame([], Membership::query()->where('membership_number', 'like', '%~del%')->pluck('id')->all()); // released card is out of every list
        $this->actingAs($this->admin())->withoutMiddleware()->getJson('/api/membership-number/check?membership_number=565656&exclude_membership_id='.$new->id)->assertJsonPath('unique', true);
    }

    public function test_restore_route_puts_the_number_back_and_keeps_the_original_record(): void
    {
        $admin = $this->admin();
        $holder = $this->card('135135');
        $slug = $holder->user->slug;
        $holderUserId = $holder->user_id;
        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '135135', 'confirmations' => 2])->assertOk();
        $this->assertTrue(User::onlyTrashed()->whereKey($holderUserId)->exists());

        $this->actingAs($admin)->withoutMiddleware()->post(route('admin.user.membership.restore', $slug))->assertRedirect();

        $back = Membership::find($holder->id);
        $this->assertNotNull($back);                                  // same row, same id: history, payments, logs still attached
        $this->assertSame('135135', $back->membership_number);
        $this->assertSame('135135', $back->slug);
        $this->assertSame($holderUserId, $back->user_id);
        $this->assertNotNull(User::find($holderUserId));
    }

    public function test_deleting_the_new_holder_normally_leaves_the_batches_as_a_plain_delete_does(): void
    {
        $admin = $this->admin();
        $old = $this->card('246246');
        $other = $this->card('246247');
        $batch = MembershipCard::create(['batch_name' => 'B', 'quantity' => 2, 'start_number' => 1, 'membership_ids' => [$old->id, $other->id]]);
        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '246246', 'confirmations' => 2])->assertOk();
        $new = $this->card('246246');
        app(\App\Actions\Membership\TakeOverMembershipNumber::class)->relink($old->id, $new);

        $this->actingAs($admin)->withoutMiddleware()->delete(route('admin.user.membership.destroy', $new->user->slug))->assertRedirect();

        // Ordinary delete behaviour is unchanged: the card is in the trash, the batch still lists ids (no relation was rewritten or lost).
        $this->assertNull(Membership::find($new->id));
        $this->assertNotNull(Membership::withTrashed()->find($new->id));
        $this->assertEqualsCanonicalizing([$new->id, $other->id], $batch->fresh()->membership_ids);
        $this->assertNotNull(Membership::find($other->id));
    }

    private function scopedAdmin(string $permission, array $attrs = []): User
    {
        foreach (['manage memberships', 'manage own memberships', 'manage partner memberships'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = User::factory()->create($attrs);
        $u->givePermissionTo($permission);

        return $u;
    }

    public function test_an_own_scoped_admin_can_only_take_numbers_of_cards_they_created(): void
    {
        $me = $this->scopedAdmin('manage own memberships');
        $mine = $this->card('313131');
        $mine->forceFill(['created_by' => $me->id])->save();
        $theirs = $this->card('424242');
        $theirs->forceFill(['created_by' => User::factory()->create()->id])->save();
        $batch = MembershipCard::create(['batch_name' => 'Theirs', 'quantity' => 1, 'start_number' => 1, 'membership_ids' => [$theirs->id]]);

        // Someone else's card: no details leak, release is refused, nothing changes.
        $this->actingAs($me)->withoutMiddleware()->getJson('/api/membership-number/check?membership_number=424242')
            ->assertJsonPath('unique', false)->assertJsonPath('member.takeover.can_take_over', false)->assertJsonMissingPath('member.takeover.batches');
        $this->actingAs($me)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '424242', 'confirmations' => 2])->assertForbidden();
        $this->assertSame('424242', Membership::find($theirs->id)->membership_number);

        // Their own card is fine.
        $this->actingAs($me)->withoutMiddleware()->getJson('/api/membership-number/check?membership_number=313131')->assertJsonPath('member.takeover.can_take_over', true);
        $this->actingAs($me)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '313131', 'confirmations' => 2])->assertOk();

        // Taking a number for a card that is not theirs to edit is refused as well.
        $stranger = $this->card('525252');
        $stranger->forceFill(['created_by' => User::factory()->create()->id])->save();
        $mine2 = $this->card('535353');
        $mine2->forceFill(['created_by' => $me->id])->save();
        $this->actingAs($me)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '535353', 'confirmations' => 2, 'exclude_membership_id' => $stranger->id])->assertForbidden();
        $this->assertNotNull(Membership::find($mine2->id));
    }

    public function test_a_partner_scoped_admin_is_limited_to_their_partner(): void
    {
        $partnerA = \App\Models\Partner::create(['title' => 'Partner '.uniqid()]);
        $partnerB = \App\Models\Partner::create(['title' => 'Partner '.uniqid()]);
        $me = $this->scopedAdmin('manage partner memberships', ['partner_id' => $partnerA->id]);
        $inside = $this->card('616161');
        $inside->forceFill(['partner_id' => $partnerA->id])->save();
        $outside = $this->card('626262');
        $outside->forceFill(['partner_id' => $partnerB->id])->save();

        $this->actingAs($me)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '626262', 'confirmations' => 2])->assertForbidden();
        $this->assertNotNull(Membership::find($outside->id));
        $this->actingAs($me)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '616161', 'confirmations' => 2])->assertOk();
        $this->assertNull(Membership::find($inside->id));
    }

    public function test_a_forged_takeover_id_cannot_rewrite_anybody_elses_batch(): void
    {
        $admin = $this->admin();
        $victim = $this->card('717171');
        $other = $this->card('727272');
        $batch = MembershipCard::create(['batch_name' => 'Victim', 'quantity' => 2, 'start_number' => 1, 'membership_ids' => [$victim->id, $other->id]]);
        $mine = $this->card('737373');
        $action = app(\App\Actions\Membership\TakeOverMembershipNumber::class);

        $this->actingAs($admin);
        $this->assertSame(0, $action->relink($victim->id, $mine));   // a live card, never released
        $victim->delete();
        $this->assertSame(0, $action->relink($victim->id, $mine));   // deleted normally, not by a takeover for this number
        $this->assertEqualsCanonicalizing([$victim->id, $other->id], $batch->fresh()->membership_ids);
    }

    public function test_restoring_a_card_whose_number_was_taken_says_so_and_loses_nothing(): void
    {
        $admin = $this->admin();
        $holder = $this->card('818181');
        $slug = $holder->user->slug;
        $name = $holder->user->name;
        $nid = $holder->national_id;
        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/membership-number/release', ['membership_number' => '818181', 'confirmations' => 2])->assertOk();
        $new = $this->card('818181');

        $res = $this->actingAs($admin)->withoutMiddleware()->post(route('admin.user.membership.restore', $slug));
        $res->assertRedirect();
        $this->assertStringContainsString('818181', (string) session('success'));

        $back = Membership::find($holder->id);
        $this->assertNotNull($back);
        $this->assertSame('818181~del'.$holder->id, $back->membership_number);   // untouched, no collision
        $this->assertSame($nid, $back->national_id);
        $this->assertSame($name, $back->user->name);
        $this->assertSame('818181', $new->fresh()->membership_number);             // the new holder keeps the number
    }
}
