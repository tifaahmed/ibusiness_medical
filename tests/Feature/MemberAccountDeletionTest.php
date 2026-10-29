<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** A member deleting their own account: the owner of a card takes the relatives along, a relative leaves alone. */
class MemberAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_owner_deletes_the_account_and_every_relative_goes_with_it(): void
    {
        $owner = User::factory()->create(['phone' => '01062587475']);
        $card = Membership::factory()->create(['user_id' => $owner->id, 'is_active' => true]);
        $relative = Membership::factory()->create([
            'user_id' => User::factory()->create()->id, 'member_type' => Membership::TYPE_RELATIVE,
            'parent_membership_id' => $card->id, 'relationship' => 'wife', 'national_id' => '29801010102345',
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/account', ['confirm' => true])
            ->assertOk()->assertJsonPath('data.removed_relatives', 1);

        $this->assertSoftDeleted('users', ['id' => $owner->id]);
        $this->assertSoftDeleted('memberships', ['id' => $card->id]);
        $this->assertSoftDeleted('memberships', ['id' => $relative->id]);
        $this->assertSoftDeleted('users', ['id' => $relative->user_id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function a_relative_deleting_themselves_leaves_the_card_alone(): void
    {
        $owner = User::factory()->create();
        $card = Membership::factory()->create(['user_id' => $owner->id, 'is_active' => true]);
        $relativeUser = User::factory()->create();
        $relative = Membership::factory()->create([
            'user_id' => $relativeUser->id, 'member_type' => Membership::TYPE_RELATIVE,
            'parent_membership_id' => $card->id, 'relationship' => 'son', 'national_id' => '29801010102345',
        ]);

        Sanctum::actingAs($relativeUser);

        $this->deleteJson('/api/v1/account', ['confirm' => true])->assertOk()->assertJsonPath('data.removed_relatives', 0);

        $this->assertSoftDeleted('memberships', ['id' => $relative->id]);
        $this->assertNotSoftDeleted('memberships', ['id' => $card->id]);
        $this->assertNotSoftDeleted('users', ['id' => $owner->id]);
    }

    /** @test */
    public function it_needs_confirmation_and_a_signed_in_member(): void
    {
        $this->deleteJson('/api/v1/account', ['confirm' => true])->assertUnauthorized();

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/account')->assertUnprocessable();
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    /** @test */
    public function signing_up_again_with_the_same_phone_brings_the_hidden_account_and_relatives_back(): void
    {
        $owner = User::factory()->create(['phone' => '01062587475']);
        $card = Membership::factory()->create(['user_id' => $owner->id, 'is_active' => true]);
        $relative = Membership::factory()->create([
            'user_id' => User::factory()->create()->id, 'member_type' => Membership::TYPE_RELATIVE,
            'parent_membership_id' => $card->id, 'relationship' => 'wife', 'national_id' => '29801010102345',
        ]);

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/v1/account', ['confirm' => true])->assertOk();

        $back = app(\App\Services\Otp\MemberRegistration::class)->register('01062587475');

        $this->assertSame($owner->id, $back->id);
        $this->assertNotSoftDeleted('memberships', ['id' => $card->id]);
        $this->assertNotSoftDeleted('memberships', ['id' => $relative->id]);
        $this->assertDatabaseCount('memberships', 2);
    }
}
