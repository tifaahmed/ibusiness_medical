<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesListFacilitiesPopupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage sales', 'web'));
        $user->assignRole($role);

        return $user;
    }

    public function test_sales_list_includes_facilities_count_and_details(): void
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $sales = Sales::create(['name' => ['en' => 'Rep One', 'ar' => 'مندوب واحد']]);
        $orphanSales = Sales::create(['name' => ['en' => 'Rep Two', 'ar' => 'مندوب اتنين']]);

        Facility::create([
            'name' => ['en' => 'Alpha Clinic', 'ar' => 'عيادة ألفا'],
            'slug' => 'alpha-clinic',
            'facility_type_id' => $type->id,
            'sales_id' => $sales->id,
        ]);
        Facility::create([
            'name' => ['en' => 'Beta Clinic', 'ar' => 'عيادة بيتا'],
            'slug' => 'beta-clinic',
            'facility_type_id' => $type->id,
            'sales_id' => $sales->id,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.sales.list'));
        $response->assertOk();

        $rows = collect($response->viewData('page')['props']['sales']['data'] ?? []);
        $repOne = $rows->firstWhere('id', $sales->id);
        $repTwo = $rows->firstWhere('id', $orphanSales->id);

        $this->assertNotNull($repOne);
        $this->assertSame(2, $repOne['facilities_count']);
        $this->assertCount(2, $repOne['facilities']);

        $facility = collect($repOne['facilities'])->firstWhere('slug', 'alpha-clinic');
        $this->assertSame('Alpha Clinic', $facility['name']['en']);
        $this->assertSame('Clinic', $facility['facility_type']['en']);

        $this->assertSame(0, $repTwo['facilities_count']);
        $this->assertCount(0, $repTwo['facilities']);
    }
}
