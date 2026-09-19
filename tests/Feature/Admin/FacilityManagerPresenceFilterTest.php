<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityManager;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Which facilities have no manager on file?" — the list's manager filter.
 */
class FacilityManagerPresenceFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage facilities', 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function seedFacilities(): void
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);

        $managed = Facility::create([
            'name' => ['en' => 'Managed Clinic', 'ar' => 'عيادة بمدير'],
            'facility_type_id' => $type->id,
        ]);
        Facility::create([
            'name' => ['en' => 'Unmanaged Clinic', 'ar' => 'عيادة بلا مدير'],
            'facility_type_id' => $type->id,
        ]);

        FacilityManager::forceCreate([
            'facility_id' => $managed->id,
            'name' => 'Dr. Manager',
            'phones' => ['01000000000'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function listedNames(?string $presence): array
    {
        $response = $this->actingAs($this->admin())->get(
            route('admin.facility.list', $presence === null ? [] : ['managers_presence' => $presence])
        );
        $response->assertOk();

        return collect($response->viewData('page')['props']['facilities']['data'] ?? [])
            ->map(fn ($facility) => is_array($facility['name']) ? ($facility['name']['en'] ?? '') : $facility['name'])
            ->sort()
            ->values()
            ->all();
    }

    public function test_every_facility_shows_when_neither_box_is_ticked(): void
    {
        $this->seedFacilities();

        $this->assertSame(['Managed Clinic', 'Unmanaged Clinic'], $this->listedNames(null));
    }

    public function test_has_manager_keeps_only_the_managed_ones(): void
    {
        $this->seedFacilities();

        $this->assertSame(['Managed Clinic'], $this->listedNames('with'));
    }

    public function test_no_manager_keeps_only_the_unmanaged_ones(): void
    {
        $this->seedFacilities();

        $this->assertSame(['Unmanaged Clinic'], $this->listedNames('without'));
    }

    public function test_an_unknown_value_filters_nothing_out(): void
    {
        $this->seedFacilities();

        $this->assertSame(['Managed Clinic', 'Unmanaged Clinic'], $this->listedNames('everything'));
    }
}
