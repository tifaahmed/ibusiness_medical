<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\User;
use App\Support\FacilityAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The facility history page: who created or edited a facility, a branch or a
 * manager, through the forms AND through the bulk tools that used to write
 * without a trace.
 */
class FacilityAuditLogTest extends TestCase
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

    public function test_the_observer_is_silent_outside_a_named_tool(): void
    {
        Facility::create(['name' => ['en' => 'Quiet', 'ar' => 'هادئ'], 'facility_type_id' => $this->type()->id]);

        $this->assertDatabaseCount('facility_logs', 0);
    }

    public function test_a_named_tool_logs_creates_and_only_the_fields_an_update_changed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $type = $this->type();

        $facility = FacilityAudit::as('import', fn () => Facility::create([
            'name' => ['en' => 'Mytra', 'ar' => 'ميترا'],
            'facility_type_id' => $type->id,
        ]));

        $created = FacilityLog::where('facility_id', $facility->id)->where('action', FacilityLog::ACTION_CREATED)->sole();
        $this->assertSame('import', $created->source);
        $this->assertSame($admin->id, $created->admin_id);
        $this->assertEquals(['en' => 'Mytra', 'ar' => 'ميترا'], $created->new_values['name']);

        FacilityAudit::as('import', fn () => $facility->update(['name' => ['en' => 'Mytra Labs', 'ar' => 'ميترا']]));

        $updated = FacilityLog::where('action', FacilityLog::ACTION_UPDATED)->sole();
        $this->assertSame(['name'], $updated->changed_fields);
        $this->assertSame('Mytra', $updated->old_values['name']['en']);
        $this->assertSame('Mytra Labs', $updated->new_values['name']['en']);

        // An update that changes nothing loggable is not an entry.
        FacilityAudit::as('import', fn () => $facility->update(['name' => ['en' => 'Mytra Labs', 'ar' => 'ميترا']]));
        $this->assertSame(1, FacilityLog::where('action', FacilityLog::ACTION_UPDATED)->count());
    }

    public function test_branches_and_managers_are_filed_on_the_facility_with_their_ids(): void
    {
        $this->actingAs($this->admin());
        $facility = Facility::create(['name' => ['en' => 'Mytra', 'ar' => 'ميترا'], 'facility_type_id' => $this->type()->id]);

        [$branch, $manager] = FacilityAudit::as('migration', fn () => [
            FacilityBranch::create(['facility_id' => $facility->id, 'name' => ['en' => 'Damietta', 'ar' => 'دمياط']]),
            FacilityManager::create(['facility_id' => $facility->id, 'name' => 'Sara']),
        ]);

        $b = FacilityLog::where('action', FacilityLog::ACTION_BRANCH_CREATED)->sole();
        $this->assertSame($branch->id, $b->new_values['branch_id']);
        $this->assertSame('migration', $b->source);
        $this->assertDatabaseHas('facility_branch_logs', ['facility_branch_id' => $branch->id, 'source' => 'migration']);

        $m = FacilityLog::where('action', FacilityLog::ACTION_MANAGER_CREATED)->sole();
        $this->assertSame($manager->id, $m->new_values['manager_id']);
    }

    public function test_the_facility_import_logs_its_rows_and_a_clear_records_who_wiped_the_table(): void
    {
        $admin = $this->admin();
        $type = $this->type();
        $gov = Governorate::factory()->create();
        $old = Facility::create(['name' => ['en' => 'Old', 'ar' => 'قديم'], 'facility_type_id' => $type->id]);

        $this->actingAs($admin)->postJson(route('admin.facility.import.commit'), [
            'mode' => 'clear',
            'rows' => [[
                'name' => 'Fresh Clinic', 'name_ar' => 'عيادة جديدة',
                'facility_type_id' => $type->id, 'governorate_id' => $gov->id,
                'branches' => [['name' => 'Main', 'name_ar' => 'الرئيسي']],
            ]],
        ])->assertOk();

        $this->assertSoftDeleted('facilities', ['id' => $old->id, 'deleted_by' => $admin->id]);
        $this->assertDatabaseHas('facility_logs', ['facility_id' => $old->id, 'action' => FacilityLog::ACTION_DELETED]);

        $fresh = Facility::where('name->en', 'Fresh Clinic')->sole();
        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $fresh->id, 'action' => FacilityLog::ACTION_CREATED, 'source' => 'import', 'admin_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('facility_logs', [
            'facility_id' => $fresh->id, 'action' => FacilityLog::ACTION_BRANCH_CREATED, 'source' => 'import',
        ]);
    }

    public function test_the_page_resolves_subjects_and_filters_by_entity_source_and_date(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $facility = Facility::create(['name' => ['en' => 'Mytra', 'ar' => 'ميترا'], 'facility_type_id' => $this->type()->id]);
        FacilityAudit::as('import', function () use ($facility) {
            FacilityBranch::create(['facility_id' => $facility->id, 'name' => ['en' => 'Damietta', 'ar' => 'دمياط']]);
            FacilityManager::create(['facility_id' => $facility->id, 'name' => 'Sara']);
        });
        FacilityLog::record($facility->id, $admin->id, FacilityLog::ACTION_UPDATED, ['canonical_url' => null], ['canonical_url' => 'x']);

        $url = route('admin.facility.logs', $facility->slug);

        $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Admin/Facility/Logs/FacilityLogsView')
            ->has('logs.data', 3));

        $this->get($url.'?entity=branch')->assertInertia(fn (Assert $p) => $p
            ->has('logs.data', 1)
            ->where('logs.data.0.entity', 'branch')
            ->where('logs.data.0.subject.label', 'Damietta')
            ->where('logs.data.0.source', 'import'));

        $this->get($url.'?entity=manager')->assertInertia(fn (Assert $p) => $p
            ->has('logs.data', 1)
            ->where('logs.data.0.subject.label', 'Sara'));

        $this->get($url.'?source=manual')->assertInertia(fn (Assert $p) => $p
            ->has('logs.data', 1)
            ->where('logs.data.0.action', FacilityLog::ACTION_UPDATED)
            ->where('logs.data.0.source', null));

        $this->get($url.'?from='.now()->addDay()->toDateString())->assertInertia(fn (Assert $p) => $p->has('logs.data', 0));
        $this->get($url.'?from='.now()->toDateString().'&to='.now()->toDateString())->assertInertia(fn (Assert $p) => $p->has('logs.data', 3));
    }
}
