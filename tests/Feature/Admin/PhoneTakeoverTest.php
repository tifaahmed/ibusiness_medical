<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Taking a phone another person holds: look at them, change / clear their phone, delete a person with no card. */
class PhoneTakeoverTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        foreach (['manage memberships', 'manage own memberships', 'manage partner memberships'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u = User::factory()->create(['phone' => '01000000001']);
        $u->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $u;
    }

    public function test_check_describes_a_person_without_a_card_as_deletable(): void
    {
        $holder = User::factory()->create(['phone' => '01011112222']);

        $this->actingAs($this->admin())->withoutMiddleware()->getJson('/api/member-field/check?field=phone&value=01011112222')
            ->assertOk()->assertJsonPath('unique', false)
            ->assertJsonPath('member.takeover.user_id', $holder->id)->assertJsonPath('member.takeover.can_delete', true);
    }

    public function test_a_person_with_a_card_cannot_be_deleted_but_can_lose_the_phone(): void
    {
        $holder = User::factory()->create(['phone' => '01022223333']);
        Membership::factory()->create(['user_id' => $holder->id, 'membership_number' => '333333', 'slug' => '333333']);
        $admin = $this->admin();

        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/member-field/phone/release', ['phone' => '01022223333', 'action' => 'delete'])->assertStatus(422);
        $this->assertNotNull(User::find($holder->id));

        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/member-field/phone/release', ['phone' => '01022223333', 'action' => 'clear'])->assertOk()->assertJsonPath('released', true);
        $this->assertNull($holder->fresh()->phone);
        $this->assertDatabaseHas('member_logs', ['admin_id' => $admin->id]);
    }

    public function test_change_gives_them_another_phone_and_checks_it(): void
    {
        $holder = User::factory()->create(['phone' => '01033334444']);
        User::factory()->create(['phone' => '01055556666']);
        $admin = $this->admin();

        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/member-field/phone/release', ['phone' => '01033334444', 'action' => 'change', 'new_phone' => '123'])->assertStatus(422);
        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/member-field/phone/release', ['phone' => '01033334444', 'action' => 'change', 'new_phone' => '01055556666'])->assertStatus(422);
        $this->assertSame('01033334444', $holder->fresh()->phone);

        $this->actingAs($admin)->withoutMiddleware()->postJson('/api/member-field/phone/release', ['phone' => '01033334444', 'action' => 'change', 'new_phone' => '01099998888'])->assertOk();
        $this->assertSame('01099998888', $holder->fresh()->phone);
    }

    public function test_a_person_with_no_card_goes_to_the_trash_not_erased(): void
    {
        $holder = User::factory()->create(['phone' => '01044445555']);

        $this->actingAs($this->admin())->withoutMiddleware()->postJson('/api/member-field/phone/release', ['phone' => '01044445555', 'action' => 'delete'])->assertOk();
        $this->assertSoftDeleted('users', ['id' => $holder->id]);
    }

    public function test_staff_accounts_are_never_touched(): void
    {
        $staff = User::factory()->create(['phone' => '01066667777']);
        $staff->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        $this->actingAs($this->admin())->withoutMiddleware()->postJson('/api/member-field/phone/release', ['phone' => '01066667777', 'action' => 'clear'])->assertStatus(422);
        $this->assertSame('01066667777', $staff->fresh()->phone);
    }
}
