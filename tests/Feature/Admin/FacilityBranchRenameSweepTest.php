<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\User;
use App\Support\BranchUniqueness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The "Fix branch names" sweep: every branch named "<facility> - <city>", with
 * numbers where a facility has more than one branch in the same city.
 *
 * The point of the sweep is that no two branches of one facility share a name,
 * so the strongest assertion here is the last one — that after a run, the rule
 * the save path enforces has nothing left to complain about.
 */
class FacilityBranchRenameSweepTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    private City $maadi;

    private City $nasr;

    protected function setUp(): void
    {
        parent::setUp();

        $governorate = Governorate::create(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $this->maadi = City::create([
            'governorate_id' => $governorate->id,
            'name' => ['en' => 'Maadi', 'ar' => 'المعادي'],
        ]);
        $this->nasr = City::create([
            'governorate_id' => $governorate->id,
            'name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر'],
        ]);

        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $this->facility = Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate(UserPermissionEnum::MANAGE_FACILITY_BRANCHES, 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function branch(?City $city, array $name, array $attributes = []): FacilityBranch
    {
        return FacilityBranch::create([
            'facility_id' => $this->facility->id,
            'city_id' => $city?->id,
            'name' => $name,
            'address' => ['en' => 'Some street', 'ar' => 'شارع ما'],
            ...$attributes,
        ]);
    }

    private function sweep(?string $mode = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->postJson(
            route('admin.facility-branch.rename.bulk.step'),
            ['ids' => [$this->facility->id], ...($mode ? ['mode' => $mode] : [])],
        );
    }

    public function test_every_branch_is_named_after_its_facility_and_city(): void
    {
        $branch = $this->branch($this->maadi, ['en' => 'Whatever', 'ar' => 'أي شيء']);

        $this->sweep()->assertOk()->assertJsonPath('results.0.state', 'ok');

        $branch->refresh();
        $this->assertSame('Mytra Labs - Maadi', $branch->getTranslation('name', 'en'));
        $this->assertSame('معامل ميترا - المعادي', $branch->getTranslation('name', 'ar'));
    }

    public function test_branches_sharing_a_city_are_numbered_from_the_second(): void
    {
        $first = $this->branch($this->maadi, ['en' => 'A', 'ar' => 'أ']);
        $second = $this->branch($this->maadi, ['en' => 'B', 'ar' => 'ب']);
        $third = $this->branch($this->maadi, ['en' => 'C', 'ar' => 'ج']);

        $this->sweep()->assertOk();

        // The first keeps the clean name; only the ones that would repeat it
        // are numbered, and the number is the same in both languages.
        $this->assertSame('Mytra Labs - Maadi', $first->refresh()->getTranslation('name', 'en'));
        $this->assertSame('Mytra Labs - Maadi 2', $second->refresh()->getTranslation('name', 'en'));
        $this->assertSame('معامل ميترا - المعادي 2', $second->getTranslation('name', 'ar'));
        $this->assertSame('Mytra Labs - Maadi 3', $third->refresh()->getTranslation('name', 'en'));
    }

    public function test_branches_in_different_cities_are_not_numbered(): void
    {
        $maadi = $this->branch($this->maadi, ['en' => 'A', 'ar' => 'أ']);
        $nasr = $this->branch($this->nasr, ['en' => 'B', 'ar' => 'ب']);

        $this->sweep()->assertOk();

        $this->assertSame('Mytra Labs - Maadi', $maadi->refresh()->getTranslation('name', 'en'));
        $this->assertSame('Mytra Labs - Nasr City', $nasr->refresh()->getTranslation('name', 'en'));
    }

    public function test_a_branch_with_no_city_is_named_after_the_facility_alone(): void
    {
        $branch = $this->branch(null, ['en' => '', 'ar' => '']);

        $this->sweep()->assertOk();

        $this->assertSame('Mytra Labs', $branch->refresh()->getTranslation('name', 'en'));
    }

    public function test_the_careful_mode_leaves_a_good_name_alone_but_fixes_a_clash(): void
    {
        $kept = $this->branch($this->maadi, ['en' => 'Riverside Clinic', 'ar' => 'عيادة النيل']);
        $clashing = $this->branch($this->maadi, ['en' => 'Riverside Clinic', 'ar' => 'عيادة النيل']);
        $blank = $this->branch($this->nasr, ['en' => '', 'ar' => '']);

        $this->sweep('missing')->assertOk();

        // Somebody's considered name stands; the copy of it and the blank one
        // are given the house style instead.
        $this->assertSame('Riverside Clinic', $kept->refresh()->getTranslation('name', 'en'));
        $this->assertSame('Mytra Labs - Maadi', $clashing->refresh()->getTranslation('name', 'en'));
        $this->assertSame('Mytra Labs - Nasr City', $blank->refresh()->getTranslation('name', 'en'));
    }

    public function test_a_name_that_differs_only_by_spacing_and_case_counts_as_a_clash(): void
    {
        $kept = $this->branch($this->maadi, ['en' => 'Riverside Clinic', 'ar' => 'عيادة النيل']);
        $sneaky = $this->branch($this->maadi, ['en' => '  riverside   clinic ', 'ar' => 'عيادة النيل']);

        $this->sweep('missing')->assertOk();

        $this->assertSame('Riverside Clinic', $kept->refresh()->getTranslation('name', 'en'));
        $this->assertSame('Mytra Labs - Maadi', $sneaky->refresh()->getTranslation('name', 'en'));
    }

    public function test_the_slug_is_left_alone_so_existing_links_keep_working(): void
    {
        $branch = $this->branch($this->maadi, ['en' => 'Old Name', 'ar' => 'الاسم القديم']);
        $slug = $branch->slug;

        $this->sweep()->assertOk();

        $this->assertSame($slug, $branch->refresh()->slug);
        $this->assertNotSame('Old Name', $branch->getTranslation('name', 'en'));
    }

    public function test_a_second_run_changes_nothing_and_reports_nothing(): void
    {
        $this->branch($this->maadi, ['en' => 'A', 'ar' => 'أ']);
        $this->branch($this->maadi, ['en' => 'B', 'ar' => 'ب']);

        $this->sweep()->assertOk()->assertJsonPath('results.0.state', 'ok');

        // Already in the house style: the sweep is idempotent, and says so by
        // reporting the facility as skipped rather than renamed again.
        $this->sweep()->assertOk()->assertJsonPath('results.0.state', 'skip');
    }

    public function test_after_a_sweep_no_branch_clashes_with_a_sibling(): void
    {
        // The mess the sweep exists for: everything in one city, several sharing
        // a name outright, one blank.
        foreach (['Clinic', 'Clinic', 'clinic', '', 'Other'] as $name) {
            $this->branch($this->maadi, ['en' => $name, 'ar' => $name === '' ? '' : 'عيادة']);
        }

        $this->sweep()->assertOk();

        foreach (FacilityBranch::where('facility_id', $this->facility->id)->get() as $branch) {
            $duplicates = BranchUniqueness::duplicatesInFacility(
                ['name' => $branch->getTranslations('name')],
                $this->facility->id,
                $branch->id,
            );

            $this->assertSame([], $duplicates, "Branch {$branch->id} still clashes after the sweep.");
        }
    }

    public function test_a_scoped_admin_does_not_take_a_name_held_by_a_branch_they_cannot_see(): void
    {
        $ownRole = Role::findOrCreate('branch-owner', 'web');
        $ownRole->givePermissionTo(Permission::findOrCreate(UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES, 'web'));
        $scoped = User::factory()->create();
        $scoped->assignRole($ownRole);

        // Somebody else's branch already holds the clean name and cannot be
        // touched, so the scoped admin's branch has to be numbered around it.
        $theirs = $this->branch($this->maadi, ['en' => 'Mytra Labs - Maadi', 'ar' => 'معامل ميترا - المعادي'], [
            'created_by' => User::factory()->create()->id,
        ]);
        $mine = $this->branch($this->maadi, ['en' => 'Mine', 'ar' => 'لي'], ['created_by' => $scoped->id]);

        $this->actingAs($scoped)
            ->postJson(route('admin.facility-branch.rename.bulk.step'), ['ids' => [$this->facility->id]])
            ->assertOk();

        $this->actingAs($scoped);
        dump([
            'scoped_id' => $scoped->id,
            'mine_id' => $mine->id,
            'has_full' => $scoped->hasPermissionTo('manage facility branches'),
            'has_own' => $scoped->hasPermissionTo('manage own facility branches'),
            'plucked' => \App\Models\FacilityBranch::query()
                ->where('created_by', $scoped->id)
                ->pluck('facility_branches.id')->all(),
        ]);

        $this->assertSame('Mytra Labs - Maadi', $theirs->refresh()->getTranslation('name', 'en'));
        $this->assertSame('Mytra Labs - Maadi 2', $mine->refresh()->getTranslation('name', 'en'));
    }

    public function test_the_work_list_names_the_facilities_with_branches(): void
    {
        $this->branch($this->maadi, ['en' => 'A', 'ar' => 'أ']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.rename.bulk.begin'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('branches.0.id', $this->facility->id);
    }
}
