<?php

namespace Tests\Feature;

use App\Actions\Membership\ActivateSubscription;
use App\Actions\Membership\CreateRelative;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** A relative sees the whole family, cannot add relatives, and becomes a primary member (keeping the old family) once they pay. */
class RelativeUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function setUpFamily(): array
    {
        $owner = User::factory()->create(['name' => 'Ahmed Ali', 'phone' => '01012345678', 'gender' => 'male']);
        $number = Membership::newNumber();
        $primary = Membership::query()->create([
            'user_id' => $owner->id, 'membership_number' => $number, 'slug' => $number, 'registration_date' => now(),
            'expiration_date' => now()->addYear(), 'plan' => 'full', 'is_active' => true, 'is_visible' => true, 'completed_at' => now(),
        ]);
        $wife = app(CreateRelative::class)->handle($primary, ['name' => 'Mona Ahmed', 'relationship' => 'wife', 'phone' => '01122223333', 'date_of_birth' => '1990-01-01']);
        $son = app(CreateRelative::class)->handle($primary, ['name' => 'Omar Ahmed', 'relationship' => 'son', 'date_of_birth' => '2015-01-01']);
        $wife->update(['is_active' => true]);
        $son->update(['is_active' => true]);

        return [$primary, $wife, $son];
    }

    /** @test */
    public function a_relative_sees_the_whole_family_with_phones_and_cannot_add_relatives(): void
    {
        [$primary, $wife] = $this->setUpFamily();
        Sanctum::actingAs($wife->user);

        $response = $this->getJson('/api/v1/family/overview')->assertOk()
            ->assertJsonPath('data.role', 'relative')
            ->assertJsonPath('data.can_add_relatives', false)
            ->assertJsonPath('data.current.0.is_primary', true)
            ->assertJsonPath('data.current.0.phone', '01012345678');

        $this->assertCount(3, $response->json('data.current'));
        $this->assertTrue(collect($response->json('data.current'))->firstWhere('is_me', true)['id'] === $wife->id);

        $this->postJson('/api/v1/family/children', ['first_name' => 'Ali', 'relationship' => 'son'])
            ->assertForbidden()->assertJsonPath('reason', 'relative_cannot_add');
        $this->postJson('/api/v1/membership/relatives-request')->assertForbidden();

        $this->getJson('/api/v1/membership')->assertOk()
            ->assertJsonPath('data.is_relative', true)->assertJsonPath('data.relatives.can_add', false);
    }

    /** @test */
    public function paying_turns_a_relative_into_a_primary_member_who_still_sees_the_old_family(): void
    {
        [$primary, $wife, $son] = $this->setUpFamily();

        $subscription = MembershipSubscription::query()->create([
            'membership_id' => $wife->id, 'method' => MembershipSubscription::METHOD_COD, 'status' => MembershipSubscription::STATUS_PENDING,
            'amount' => 500, 'months' => 12,
        ]);
        app(ActivateSubscription::class)->handle($subscription, null);

        $wife->refresh();
        $this->assertSame(Membership::TYPE_PRIMARY, $wife->member_type);
        $this->assertNull($wife->parent_membership_id);
        $this->assertSame($primary->id, (int) $wife->former_parent_membership_id);
        $this->assertSame('full', $wife->plan);

        Sanctum::actingAs($wife->user);
        $this->getJson('/api/v1/family/overview')->assertOk()
            ->assertJsonPath('data.role', 'primary')
            ->assertJsonPath('data.can_add_relatives', true)
            ->assertJsonPath('data.current.0.is_me', true)
            ->assertJsonPath('data.former.0.membership_number', $primary->membership_number);
        $this->assertCount(1, $this->getJson('/api/v1/family/overview')->json('data.current'));
        $this->assertCount(2, $this->getJson('/api/v1/family/overview')->json('data.former')); // the old head + the son
    }
}
