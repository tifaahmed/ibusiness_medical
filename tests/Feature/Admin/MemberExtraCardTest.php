<?php

namespace Tests\Feature\Admin;

use App\Actions\Membership\CreateRelative;
use App\Enums\User\UserRoleEnum;
use App\Models\MemberExtraCard;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** An admin gives a member (primary or relative) several physical / soft cards, same number or a different one. */
class MemberExtraCardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        Permission::findOrCreate('manage memberships', 'web');
        Permission::findOrCreate('manage own memberships', 'web');
        Permission::findOrCreate('manage partner memberships', 'web');
        $u->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $u;
    }

    private function card(): Membership
    {
        $user = User::factory()->create(['name' => 'Ahmed Ali Hassan', 'gender' => 'male']);

        return Membership::factory()->create(['user_id' => $user->id, 'is_active' => true, 'is_visible' => true]);
    }

    public function test_several_cards_with_the_same_number_and_a_different_one(): void
    {
        $admin = $this->admin();
        $card = $this->card();
        $relative = app(CreateRelative::class)->handle($card, ['name' => 'Mona Hassan Ali', 'relationship' => 'wife'], Membership::ADDED_BY_ADMIN);

        $this->actingAs($admin)->withoutMiddleware()
            ->post(route('admin.member-extra-cards.store'), ['membership_id' => $card->id, 'kind' => 'physical', 'quantity' => 3])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->withoutMiddleware()
            ->post(route('admin.member-extra-cards.store'), ['membership_id' => $relative->id, 'kind' => 'soft', 'card_number' => '777001'])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, MemberExtraCard::where('membership_id', $card->id)->whereNull('card_number')->count());
        $this->assertSame('777001', MemberExtraCard::where('membership_id', $relative->id)->value('card_number'));

        // The different number opens the same member.
        $this->assertTrue(Membership::query()->matchingNumber('777001')->whereKey($relative->id)->exists());
    }

    public function test_a_number_held_by_someone_else_is_refused(): void
    {
        $admin = $this->admin();
        $a = $this->card();
        $b = $this->card();

        $this->actingAs($admin)->withoutMiddleware()
            ->post(route('admin.member-extra-cards.store'), ['membership_id' => $a->id, 'kind' => 'soft', 'card_number' => $b->membership_number])
            ->assertSessionHasErrors('card_number');

        MemberExtraCard::create(['membership_id' => $b->id, 'kind' => 'soft', 'card_number' => '888002']);
        $this->actingAs($admin)->withoutMiddleware()
            ->post(route('admin.member-extra-cards.store'), ['membership_id' => $a->id, 'kind' => 'soft', 'card_number' => '888002'])
            ->assertSessionHasErrors('card_number');

        $this->assertSame(0, MemberExtraCard::where('membership_id', $a->id)->count());
    }

    public function test_a_card_without_its_own_number_follows_a_renumbered_member(): void
    {
        $card = $this->card();
        $extra = MemberExtraCard::create(['membership_id' => $card->id, 'kind' => 'physical']);
        $card->forceFill(['membership_number' => '555123'])->save();

        $this->assertSame('555123', $extra->fresh()->number());
    }
}
