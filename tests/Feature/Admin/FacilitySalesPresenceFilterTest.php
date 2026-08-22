<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Which facilities has nobody been assigned to?" — a question the rep
 * dropdown cannot ask, because it can only name a rep that exists.
 */
class FacilitySalesPresenceFilterTest extends TestCase
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
        $sales = Sales::create(['name' => ['en' => 'Rep One', 'ar' => 'مندوب واحد']]);

        Facility::create([
            'name' => ['en' => 'Assigned Clinic', 'ar' => 'عيادة مسندة'],
            'facility_type_id' => $type->id,
            'sales_id' => $sales->id,
        ]);
        Facility::create([
            'name' => ['en' => 'Orphan Clinic', 'ar' => 'عيادة بلا مندوب'],
            'facility_type_id' => $type->id,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function listedNames(?string $presence): array
    {
        $response = $this->actingAs($this->admin())->get(
            route('admin.facility.list', $presence === null ? [] : ['sales_presence' => $presence])
        );
        $response->assertOk();

        return collect($response->viewData('page')['props']['facilities']['data'] ?? [])
            ->map(fn ($facility) => is_array($facility['name']) ? ($facility['name']['en'] ?? '') : $facility['name'])
            ->sort()
            ->values()
            ->all();
    }

    public function test_the_list_shows_every_facility_when_neither_box_is_ticked(): void
    {
        $this->seedFacilities();

        $this->assertSame(['Assigned Clinic', 'Orphan Clinic'], $this->listedNames(null));
    }

    public function test_has_a_sales_rep_keeps_only_the_assigned_ones(): void
    {
        $this->seedFacilities();

        $this->assertSame(['Assigned Clinic'], $this->listedNames('with'));
    }

    public function test_no_sales_rep_keeps_only_the_unassigned_ones(): void
    {
        $this->seedFacilities();

        $this->assertSame(['Orphan Clinic'], $this->listedNames('without'));
    }

    public function test_an_unknown_value_filters_nothing_out(): void
    {
        $this->seedFacilities();

        // The value reaches a whereNull/whereNotNull decision, so anything the
        // screen did not send is dropped rather than guessed at.
        $this->assertSame(['Assigned Clinic', 'Orphan Clinic'], $this->listedNames('everything'));
    }

    public function test_the_export_honours_the_same_filter(): void
    {
        $this->seedFacilities();

        $response = $this->actingAs($this->admin())->get(
            route('admin.facility.export', ['sales_presence' => 'without', 'include_branches' => 1])
        );
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'facility-export').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        try {
            $spreadsheet = IOFactory::load($path);
            $rows = $spreadsheet->getSheetByName('Facilities')->toArray();
            $spreadsheet->disconnectWorksheets();

            $names = collect($rows)->pluck(1)->filter()->all();
            $this->assertContains('Orphan Clinic', $names);
            $this->assertNotContains('Assigned Clinic', $names);

            // The report says which way it was filtered.
            $filter = collect($rows)->first(fn ($row) => ($row[0] ?? null) === 'Sales rep');
            $this->assertSame('None — only facilities with no rep', $filter[1]);
        } finally {
            @unlink($path);
        }
    }
}
