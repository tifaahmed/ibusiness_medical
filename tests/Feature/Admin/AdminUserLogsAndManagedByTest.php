<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\AdminUserLog;
use App\Models\Facility;
use App\Models\FacilityLog;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserLogsAndManagedByTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create(['name' => 'Root']);
        $u->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $u;
    }

    private function adminUser(string $name = 'Staff'): User
    {
        $u = User::factory()->create(['name' => $name]);
        $u->assignRole(Role::findOrCreate('admin', 'web'));

        return $u;
    }

    private function facility(?int $managedBy = null, string $n = 'F'): Facility
    {
        $type = FacilityType::first() ?? FacilityType::create(['name' => ['ar' => 'نوع', 'en' => 'Type']]);

        return Facility::create([
            'name' => ['ar' => $n, 'en' => $n],
            'facility_type_id' => $type->id,
            'contracted_at' => '2026-01-01',
            'managed_by' => $managedBy,
        ]);
    }

    public function test_blocking_persists_and_is_logged(): void
    {
        $root = $this->superAdmin();
        $staff = $this->adminUser();

        $this->actingAs($root)->put(route('admin.admin-users.update', $staff->id), [
            'name' => $staff->name,
            'email' => $staff->email,
            'roles' => ['admin'],
            'dashboard_blocked' => true,
        ])->assertRedirect();

        $this->assertNotNull($staff->fresh()->dashboard_blocked_at);
        $log = AdminUserLog::where('user_id', $staff->id)->where('action', 'blocked')->first();
        $this->assertNotNull($log);
        $this->assertSame($root->id, $log->admin_id);

        $this->actingAs($root)->get(route('admin.admin-users.logs', $staff->id))->assertOk();
        $this->actingAs($root)->get(route('admin.admin-users.index', ['blocked' => 1]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('admins.data.0.dashboard_blocked', true));
    }

    public function test_bulk_assign_preview_and_write(): void
    {
        $root = $this->superAdmin();
        $a = $this->adminUser('A');
        $b = $this->adminUser('B');
        $empty = $this->facility(null, 'E');
        $owned = $this->facility($a->id, 'O');

        $preview = $this->actingAs($root)->postJson(route('admin.facility.managed-by.preview'), [
            'facility_ids' => [$empty->id, $owned->id],
            'target' => $b->id,
        ])->assertOk()->json();

        $this->assertSame(2, $preview['total']);
        $this->assertSame(1, $preview['empty']);
        $this->assertSame($a->id, $preview['taken_from'][0]['user_id']);

        $this->actingAs($root)->postJson(route('admin.facility.managed-by.assign'), [
            'facility_ids' => [$empty->id, $owned->id],
            'target' => $b->id,
        ])->assertOk()->assertJson(['changed' => 2]);

        $this->assertSame($b->id, $empty->fresh()->managed_by);
        $this->assertSame($b->id, $owned->fresh()->managed_by);
        $this->assertSame(2, FacilityLog::where('source', 'managed_by_bulk')->count());

        // Same again: nothing to move, nothing logged.
        $this->actingAs($root)->postJson(route('admin.facility.managed-by.assign'), [
            'facility_ids' => [$empty->id], 'target' => $b->id,
        ])->assertJson(['changed' => 0]);

        // Clear.
        $this->actingAs($root)->postJson(route('admin.facility.managed-by.assign'), [
            'all_matching' => true, 'managed_by' => $b->id, 'target' => '',
        ])->assertJson(['changed' => 2]);
        $this->assertNull($owned->fresh()->managed_by);

        $this->actingAs($root)->get(route('admin.facility.managed-by.page'))->assertOk();
    }
}
