<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Branches added from the facility form are written on their own, and no two
 * branches of one facility may share a name or an address.
 */
class FacilityBranchInlineSaveTest extends TestCase
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

    /**
     * @return array{governorate_id: int, city_id: int}
     */
    private function place(): array
    {
        $governorate = Governorate::create(['name' => ['en' => 'Damietta', 'ar' => 'دمياط']]);
        $city = City::create([
            'governorate_id' => $governorate->id,
            'name' => ['en' => 'New Damietta', 'ar' => 'دمياط الجديدة'],
        ]);

        return ['governorate_id' => $governorate->id, 'city_id' => $city->id];
    }

    private function facility(): Facility
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);

        return Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);
    }

    public function test_a_branch_is_stored_immediately_from_the_facility_form(): void
    {
        $admin = $this->admin();
        $facility = $this->facility();

        $response = $this->actingAs($admin)->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
                'address' => ['en' => 'Nile street', 'ar' => 'شارع النيل'],
                'google_location_url' => 'https://maps.app.goo.gl/abc',
                'phone' => ['0663400006'],
                ...$this->place(),
            ],
        );

        $response->assertOk()
            ->assertJsonPath('created', true)
            ->assertJsonPath('branch.name.en', 'Damietta')
            ->assertJsonPath('branch.google_location_url', 'https://maps.app.goo.gl/abc')
            ->assertJsonPath('branch.created_by_name', $admin->name);

        $this->assertDatabaseCount('facility_branches', 1);
        $this->assertSame($facility->id, FacilityBranch::first()->facility_id);
    }

    public function test_a_branch_may_not_repeat_a_name_used_in_the_same_facility(): void
    {
        $facility = $this->facility();
        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
            'address' => ['en' => 'Nile street', 'ar' => 'شارع النيل'],
        ]);

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                // Same name, typed with different case and spacing.
                'name' => ['en' => '  damietta ', 'ar' => 'دمياط'],
                'address' => ['en' => 'Another street', 'ar' => 'شارع آخر'],
                ...$this->place(),
            ],
        )->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('facility_branches', 1);
    }

    public function test_a_branch_may_not_repeat_an_address_used_in_the_same_facility(): void
    {
        $facility = $this->facility();
        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
            'address' => ['en' => 'Nile street', 'ar' => 'شارع النيل'],
        ]);

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Port Said', 'ar' => 'بورسعيد'],
                'address' => ['en' => 'Nile Street', 'ar' => 'شارع النيل'],
                ...$this->place(),
            ],
        )->assertStatus(422)->assertJsonValidationErrors('address');
    }

    public function test_the_same_name_is_free_to_use_under_another_facility(): void
    {
        $first = $this->facility();
        $second = Facility::create([
            'name' => ['en' => 'Other Labs', 'ar' => 'معامل أخرى'],
            'facility_type_id' => $first->facility_type_id,
        ]);

        FacilityBranch::create([
            'facility_id' => $first->id,
            'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
        ]);

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $second->slug),
            ['name' => ['en' => 'Damietta', 'ar' => 'دمياط'], ...$this->place()],
        )->assertOk();

        $this->assertDatabaseCount('facility_branches', 2);
    }

    public function test_editing_a_branch_does_not_collide_with_itself(): void
    {
        $facility = $this->facility();
        $branch = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
            'address' => ['en' => 'Nile street', 'ar' => 'شارع النيل'],
        ]);

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'id' => $branch->id,
                'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
                'address' => ['en' => 'Nile street', 'ar' => 'شارع النيل'],
                'google_location_url' => 'https://maps.app.goo.gl/xyz',
                ...$this->place(),
            ],
        )->assertOk()->assertJsonPath('created', false);

        $this->assertSame('https://maps.app.goo.gl/xyz', $branch->fresh()->google_location_url);
    }

    public function test_the_full_facility_save_rejects_two_branches_sharing_a_name(): void
    {
        $facility = $this->facility();

        $this->actingAs($this->admin())->put(
            route('admin.facility.update', $facility->slug),
            [
                'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
                'facility_type_id' => $facility->facility_type_id,
                'branches' => [
                    ['name' => ['en' => 'Damietta', 'ar' => 'دمياط']],
                    ['name' => ['en' => 'damietta', 'ar' => 'بورسعيد']],
                ],
            ],
        )->assertSessionHasErrors('branches.1.name.en');
    }

    public function test_the_full_facility_save_stores_the_google_location_url(): void
    {
        $facility = $this->facility();

        $this->actingAs($this->admin())->put(
            route('admin.facility.update', $facility->slug),
            [
                'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
                'facility_type_id' => $facility->facility_type_id,
                'branches' => [
                    [
                        'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
                        'google_location_url' => 'https://maps.app.goo.gl/abc',
                    ],
                ],
            ],
        )->assertSessionHasNoErrors();

        $this->assertSame('https://maps.app.goo.gl/abc', FacilityBranch::first()->google_location_url);
    }
}
