<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Soft delete for facilities, their branches and their managers: a facility
 * moved to the trash takes its branches and managers with it, all three can
 * be restored together, a permanent delete removes all three for good, and
 * every step is written to the audit log.
 */
class FacilitySoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage facilities', 'web'));
        $role->givePermissionTo(Permission::findOrCreate('manage facility branches', 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function facilityWithBranchAndManager(): array
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $facility = Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);
        $branch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
        ]);
        $manager = FacilityManager::create([
            'facility_id' => $facility->id,
            'name' => 'Manager One',
        ]);

        return [$facility, $branch, $manager];
    }

    public function test_deleting_a_facility_soft_deletes_it_with_its_branches_and_managers(): void
    {
        $admin = $this->admin();
        [$facility, $branch, $manager] = $this->facilityWithBranchAndManager();

        $this->actingAs($admin)
            ->delete(route('admin.facility.destroy', $facility->slug))
            ->assertRedirect(route('admin.facility.list'));

        $this->assertSoftDeleted('facilities', ['id' => $facility->id, 'deleted_by' => $admin->id]);
        $this->assertSoftDeleted('facility_branches', ['id' => $branch->id, 'deleted_by' => $admin->id]);
        $this->assertSoftDeleted('facility_managers', ['id' => $manager->id, 'deleted_by' => $admin->id]);

        // Hidden from the normal list/show routes.
        $this->assertNull(Facility::find($facility->id));
        $this->actingAs($admin)->get(route('admin.facility.show', $facility->slug))->assertNotFound();

        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $facility->id,
            'admin_id' => $admin->id,
            'action' => FacilityLog::ACTION_DELETED,
        ]);
        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $facility->id,
            'action' => FacilityLog::ACTION_MANAGER_DELETED,
        ]);
    }

    public function test_restoring_a_facility_restores_the_branches_and_managers_deleted_with_it(): void
    {
        $admin = $this->admin();
        [$facility, $branch, $manager] = $this->facilityWithBranchAndManager();

        $this->actingAs($admin)->delete(route('admin.facility.destroy', $facility->slug));

        $this->actingAs($admin)
            ->post(route('admin.facility.restore', $facility->slug))
            ->assertRedirect(route('admin.facility.trash'));

        $this->assertDatabaseHas('facilities', ['id' => $facility->id, 'deleted_at' => null, 'deleted_by' => null]);
        $this->assertDatabaseHas('facility_branches', ['id' => $branch->id, 'deleted_at' => null, 'deleted_by' => null]);
        $this->assertDatabaseHas('facility_managers', ['id' => $manager->id, 'deleted_at' => null, 'deleted_by' => null]);

        $this->assertNotNull(Facility::find($facility->id));

        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $facility->id,
            'admin_id' => $admin->id,
            'action' => FacilityLog::ACTION_RESTORED,
        ]);
    }

    public function test_restoring_a_facility_does_not_restore_a_branch_deleted_independently_beforehand(): void
    {
        $admin = $this->admin();
        [$facility, $branch] = $this->facilityWithBranchAndManager();

        // The branch is deleted on its own, first.
        $this->actingAs($admin)->delete(route('admin.facility-branch.destroy', $branch->slug));
        $this->assertSoftDeleted('facility_branches', ['id' => $branch->id]);

        // Then, later, the whole facility (with no branches left to take).
        $this->actingAs($admin)->delete(route('admin.facility.destroy', $facility->slug));

        $this->actingAs($admin)->post(route('admin.facility.restore', $facility->slug));

        $this->assertNotNull(Facility::find($facility->id));
        // The branch deleted independently, earlier, must stay in the trash.
        $this->assertSoftDeleted('facility_branches', ['id' => $branch->id]);
    }

    public function test_force_deleting_a_facility_removes_it_and_its_branches_and_managers_for_good(): void
    {
        $admin = $this->admin();
        [$facility, $branch, $manager] = $this->facilityWithBranchAndManager();

        $this->actingAs($admin)->delete(route('admin.facility.destroy', $facility->slug));

        $this->actingAs($admin)
            ->delete(route('admin.facility.force-delete', $facility->slug))
            ->assertRedirect(route('admin.facility.trash'));

        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
        $this->assertDatabaseMissing('facility_branches', ['id' => $branch->id]);
        $this->assertDatabaseMissing('facility_managers', ['id' => $manager->id]);

        // The audit trail survives, with its facility_id nulled by the FK.
        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => null,
            'action' => FacilityLog::ACTION_FORCE_DELETED,
        ]);
    }

    public function test_a_facility_must_be_in_the_trash_before_it_can_be_force_deleted(): void
    {
        $admin = $this->admin();
        [$facility] = $this->facilityWithBranchAndManager();

        // Never deleted — onlyTrashed() must refuse it.
        $this->actingAs($admin)
            ->delete(route('admin.facility.force-delete', $facility->slug))
            ->assertNotFound();

        $this->assertNotNull(Facility::find($facility->id));
    }

    public function test_removing_a_branch_and_manager_from_the_facility_form_soft_deletes_rather_than_drops_them(): void
    {
        $admin = $this->admin();
        [$facility, $branch, $manager] = $this->facilityWithBranchAndManager();

        // Submitting the full form with neither branch nor manager listed is
        // exactly what happens when an admin removes their cards and saves.
        $this->actingAs($admin)->put(route('admin.facility.update', $facility->slug), [
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $facility->facility_type_id,
            'branches' => [],
            'managers' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSoftDeleted('facility_branches', ['id' => $branch->id, 'deleted_by' => $admin->id]);
        $this->assertSoftDeleted('facility_managers', ['id' => $manager->id, 'deleted_by' => $admin->id]);

        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $facility->id,
            'action' => FacilityLog::ACTION_BRANCH_DELETED,
        ]);
        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $facility->id,
            'action' => FacilityLog::ACTION_MANAGER_DELETED,
        ]);
    }

    public function test_restoring_a_manager_from_the_facility_edit_page_brings_it_back(): void
    {
        $admin = $this->admin();
        [$facility, , $manager] = $this->facilityWithBranchAndManager();

        $manager->deleted_by = $admin->id;
        $manager->save();
        $manager->delete();

        $this->actingAs($admin)
            ->postJson(route('admin.facility.manager.restore', [$facility->slug, $manager->id]))
            ->assertOk()
            ->assertJsonPath('manager.id', $manager->id);

        $this->assertDatabaseHas('facility_managers', ['id' => $manager->id, 'deleted_at' => null, 'deleted_by' => null]);

        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $facility->id,
            'action' => FacilityLog::ACTION_MANAGER_RESTORED,
        ]);
    }

    public function test_standalone_branch_create_and_update_are_mirrored_onto_the_facility_timeline(): void
    {
        $admin = $this->admin();
        [$facility] = $this->facilityWithBranchAndManager();

        $governorate = Governorate::create(['name' => ['en' => 'Damietta', 'ar' => 'دمياط']]);
        $city = City::create(['governorate_id' => $governorate->id, 'name' => ['en' => 'New Damietta', 'ar' => 'دمياط الجديدة']]);

        $createResponse = $this->actingAs($admin)->post(route('admin.facility-branch.store'), [
            'facility_id' => $facility->id,
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'name' => ['en' => 'Port Said', 'ar' => 'بورسعيد'],
            'address' => ['en' => 'Corniche street', 'ar' => 'شارع الكورنيش'],
        ]);
        $createResponse->assertSessionHasNoErrors();

        $branch = FacilityBranch::where('facility_id', $facility->id)->latest('id')->first();

        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $facility->id,
            'action' => FacilityLog::ACTION_BRANCH_CREATED,
        ]);

        $updateResponse = $this->actingAs($admin)->put(route('admin.facility-branch.update', $branch->slug), [
            'facility_id' => $facility->id,
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'name' => ['en' => 'Port Said Renamed', 'ar' => 'بورسعيد المعدل'],
            'address' => ['en' => 'Corniche street', 'ar' => 'شارع الكورنيش'],
        ]);
        $updateResponse->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $facility->id,
            'action' => FacilityLog::ACTION_BRANCH_UPDATED,
        ]);
    }

    public function test_a_soft_deleted_branch_id_cannot_be_reattached_through_a_facility_save(): void
    {
        $admin = $this->admin();
        [$facility, $branch] = $this->facilityWithBranchAndManager();

        $branch->deleted_by = $admin->id;
        $branch->save();
        $branch->delete();

        $this->actingAs($admin)->put(route('admin.facility.update', $facility->slug), [
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $facility->facility_type_id,
            'branches' => [
                ['id' => $branch->id, 'name' => ['en' => 'Damietta', 'ar' => 'دمياط']],
            ],
        ])->assertSessionHasErrors('branches.0.id');
    }
}
