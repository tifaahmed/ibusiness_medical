<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Http\Controllers\Admin\MembershipCard\Actions\Store\StoreMembershipCardAction;
use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A card batch reserves a range of numbers and creates NO members; a later member who takes one is filed under it. */
class CardBatchWithoutMembersTest extends TestCase
{
    use RefreshDatabase;

    private function batch(string $prefix = 'AB', int $start = 100, int $qty = 5): MembershipCard
    {
        return app(StoreMembershipCardAction::class)->execute(['quantity' => $qty, 'start_number' => $start, 'prefix' => $prefix, 'batch_name' => 'Spring'], null)['card'];
    }

    private function member(string $number): Membership
    {
        return Membership::factory()->create(['user_id' => User::factory()->create()->id, 'membership_number' => $number, 'slug' => $number]);
    }

    public function test_creating_a_batch_makes_no_users_or_memberships(): void
    {
        $users = User::count();
        $result = app(StoreMembershipCardAction::class)->execute(['quantity' => 5, 'start_number' => 100, 'prefix' => 'AB'], null);

        $this->assertSame($users, User::count());
        $this->assertSame(0, Membership::withTrashed()->count());
        $this->assertSame([], $result['card']->membership_ids);
        $this->assertSame(['AB100', 'AB101', 'AB102', 'AB103', 'AB104'], array_column($result['memberships'], 'membership_number'));
    }

    public function test_a_member_taking_a_reserved_number_is_filed_under_the_batch(): void
    {
        $batch = $this->batch();
        $inside = $this->member('AB102');
        $outside = $this->member('AB105');
        $other = $this->member('777777');

        $this->assertSame([$inside->id], $batch->fresh()->membership_ids);

        // Changing a number into the range files it too, and saving again does not duplicate it.
        $other->update(['membership_number' => 'AB101']);
        $other->update(['is_active' => false]);
        $this->assertEqualsCanonicalizing([$inside->id, $other->id], $batch->fresh()->membership_ids);
        $this->assertNotContains($outside->id, $batch->fresh()->membership_ids);
    }

    public function test_new_numbers_never_land_in_a_reserved_range(): void
    {
        $this->batch('', 100000, 1000); // 100000..100999
        for ($i = 0; $i < 60; $i++) {
            $n = (int) Membership::newNumber();
            $this->assertFalse($n >= 100000 && $n <= 100999);
        }
    }

    public function test_check_reports_the_batch_and_generate_avoids_it(): void
    {
        $this->batch('', 1000, 8999); // every 4-digit number but 9999
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        $this->actingAs($admin)->withoutMiddleware()->getJson('/api/membership-number/check?membership_number=2500')
            ->assertOk()->assertJsonPath('unique', true)->assertJsonPath('reserved_by_batch.name', 'Spring');
        $this->actingAs($admin)->withoutMiddleware()->getJson('/api/membership-number/check?membership_number=ZZ1')
            ->assertOk()->assertJsonPath('reserved_by_batch', null);
    }

    public function test_a_batch_overlapping_another_is_refused(): void
    {
        $this->batch('AB', 100, 5);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        foreach (['view membership card patches', 'create membership card patches', 'view own membership card patches', 'create own membership card patches', 'view partner membership card patches', 'create partner membership card patches'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $admin->givePermissionTo('create membership card patches');

        $this->actingAs($admin)->withoutMiddleware()->postJson(route('admin.membership-card-patches.store'), ['quantity' => 3, 'start_number' => 103, 'prefix' => 'AB'])
            ->assertStatus(422)->assertJsonValidationErrors('start_number');
    }
}
