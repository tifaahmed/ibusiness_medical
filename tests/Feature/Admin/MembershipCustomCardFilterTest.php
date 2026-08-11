<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\CardLayout;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A member's card either follows its template — and so changes whenever the
 * design does — or was changed away from it and keeps its own look. The
 * memberships list says which, and can be narrowed to either.
 */
class MembershipCustomCardFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage memberships', 'web'));
        $user->assignRole($role);

        return $user;
    }

    /** @return array{0: Membership, 1: Membership} custom, then default */
    private function seedMemberships(): array
    {
        $custom = Membership::factory()->create([
            'membership_number' => 'MEM-CUSTOM',
            'completed_at' => now(),
        ]);
        $default = Membership::factory()->create([
            'membership_number' => 'MEM-DEFAULT',
            'completed_at' => now(),
        ]);

        // Its own layout — this card no longer follows the design.
        CardLayout::create([
            'membership_id' => $custom->id,
            'layout' => ['slogan' => ['x' => 0.2, 'y' => 0.5, 'width' => 0.2, 'height' => 0.05]],
        ]);
        // A saved card with no layout of its own still follows the design.
        CardLayout::create(['membership_id' => $default->id]);

        return [$custom, $default];
    }

    public function test_the_list_says_which_cards_keep_their_own_look(): void
    {
        [$custom, $default] = $this->seedMemberships();

        $this->actingAs($this->admin())
            ->get('/admin/user/membership')
            ->assertOk()
            ->assertInertia(function ($page) use ($custom, $default) {
                $byNumber = collect($page->toArray()['props']['users']['data'])
                    ->keyBy(fn ($row) => $row['membership']['membership_number']);

                $this->assertTrue($byNumber['MEM-CUSTOM']['membership']['has_custom_card']);
                $this->assertFalse($byNumber['MEM-DEFAULT']['membership']['has_custom_card']);
            });
    }

    public function test_the_list_can_be_narrowed_to_either_kind(): void
    {
        $this->seedMemberships();

        $this->actingAs($this->admin())
            ->get('/admin/user/membership?has_custom_card=1')
            ->assertOk()
            ->assertInertia(function ($page) {
                $rows = collect($page->toArray()['props']['users']['data'])->pluck('membership.membership_number');
                $this->assertContains('MEM-CUSTOM', $rows->all());
                $this->assertNotContains('MEM-DEFAULT', $rows->all());
            });

        $this->actingAs($this->admin())
            ->get('/admin/user/membership?has_custom_card=0')
            ->assertOk()
            ->assertInertia(function ($page) {
                $rows = collect($page->toArray()['props']['users']['data'])->pluck('membership.membership_number');
                $this->assertContains('MEM-DEFAULT', $rows->all());
                $this->assertNotContains('MEM-CUSTOM', $rows->all());
            });
    }
}
