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
 * A manager's phones are stored the way a branch's are: one typed entry per
 * number, split before validation so "010… / 011…" becomes two. Every path
 * that writes a manager — the full facility save, and the form's own manager
 * modal — has to agree on that shape.
 */
class FacilityManagerPhonesTest extends TestCase
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

    private function type(): FacilityType
    {
        return FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
    }

    public function test_a_facility_can_be_created_with_a_manager(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->admin())->post(route('admin.facility.store'), [
            'name' => ['en' => 'Repro Center', 'ar' => 'مركز التجربة'],
            'facility_type_id' => $this->type()->id,
            'managers' => [
                ['id' => null, 'name' => 'Ahmed', 'position' => 'Director', 'phones' => ['01020709993 / 0223456789']],
            ],
        ]);

        $response->assertRedirect();

        $manager = Facility::where('name->en', 'Repro Center')->firstOrFail()->managers()->firstOrFail();
        $this->assertSame('Ahmed', $manager->name);
        $this->assertEquals([
            ['number' => '01020709993', 'type' => 'phone'],
            ['number' => '0223456789', 'type' => 'landline'],
        ], $manager->phones);
    }

    public function test_a_manager_is_stored_immediately_from_the_facility_form(): void
    {
        $facility = Facility::create([
            'name' => ['en' => 'Inline Center', 'ar' => 'مركز فوري'],
            'facility_type_id' => $this->type()->id,
        ]);

        $response = $this->actingAs($this->admin())->postJson(
            route('admin.facility.manager.save', $facility->slug),
            ['name' => 'Ahmed', 'position' => 'Director', 'phones' => ['01020709993 / 0223456789']],
        );

        $response->assertOk()
            ->assertJsonPath('created', true)
            ->assertJsonPath('manager.name', 'Ahmed')
            ->assertJsonPath('manager.phones', [
                ['number' => '01020709993', 'type' => 'phone'],
                ['number' => '0223456789', 'type' => 'landline'],
            ]);

        $this->assertDatabaseCount('facility_managers', 1);
        $this->assertSame($facility->id, FacilityManager::first()->facility_id);
    }

    public function test_saving_the_same_manager_again_updates_it_rather_than_adding_a_second(): void
    {
        $facility = Facility::create([
            'name' => ['en' => 'Inline Center', 'ar' => 'مركز فوري'],
            'facility_type_id' => $this->type()->id,
        ]);

        $manager = FacilityManager::create([
            'facility_id' => $facility->id,
            'name' => 'Ahmed',
            'phones' => ['01020709993'],
        ]);

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.manager.save', $facility->slug),
            [
                'id' => $manager->id,
                'name' => 'Ahmed Ali',
                'position' => 'Director',
                'phones' => [['number' => '01111111111', 'type' => 'whatsapp']],
            ],
        )->assertOk()->assertJsonPath('created', false);

        $this->assertDatabaseCount('facility_managers', 1);
        $this->assertSame('Ahmed Ali', $manager->fresh()->name);

        // The type the form chose is kept, not re-guessed from the number.
        $this->assertEquals([['number' => '01111111111', 'type' => 'whatsapp']], $manager->fresh()->phones);
    }

    public function test_a_manager_may_not_be_moved_onto_another_facility(): void
    {
        $type = $this->type();
        $mine = Facility::create(['name' => ['en' => 'Mine', 'ar' => 'لي'], 'facility_type_id' => $type->id]);
        $other = Facility::create(['name' => ['en' => 'Other', 'ar' => 'أخرى'], 'facility_type_id' => $type->id]);

        $manager = FacilityManager::create([
            'facility_id' => $other->id,
            'name' => 'Ahmed',
        ]);

        // The id exists, so validation passes; the facility scope is what stops it.
        $this->actingAs($this->admin())->postJson(
            route('admin.facility.manager.save', $mine->slug),
            ['id' => $manager->id, 'name' => 'Stolen'],
        )->assertStatus(500);

        $this->assertSame($other->id, $manager->fresh()->facility_id);
        $this->assertSame('Ahmed', $manager->fresh()->name);
    }

    public function test_a_manager_can_be_added_while_updating_a_facility(): void
    {
        $this->withoutExceptionHandling();

        $facility = Facility::create([
            'name' => ['en' => 'Existing Center', 'ar' => 'مركز قائم'],
            'facility_type_id' => $this->type()->id,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.facility.update', $facility->slug), [
            'name' => ['en' => 'Existing Center', 'ar' => 'مركز قائم'],
            'facility_type_id' => $facility->facility_type_id,
            'managers' => [
                ['id' => null, 'name' => 'Mona', 'position' => 'Manager', 'phones' => ['01020709993']],
            ],
        ]);

        $response->assertRedirect();

        $manager = $facility->managers()->firstOrFail();
        $this->assertSame('Mona', $manager->name);
        $this->assertEquals([['number' => '01020709993', 'type' => 'phone']], $manager->phones);
    }
}
