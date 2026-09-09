<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The branch list's "No address" switch: the rows an admin still has to write
 * an address for, whether they have none at all or only one of the two
 * languages.
 */
class FacilityBranchNoAddressFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage facility branches', 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function facility(): Facility
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);

        return Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);
    }

    /** @return array<string, int> the branch id of each kind */
    private function branches(): array
    {
        $facility = $this->facility();
        $make = fn (string $name, ?array $address) => FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => $name, 'ar' => $name],
            'address' => $address,
        ])->id;

        return [
            'complete' => $make('Complete', ['ar' => 'شارع النيل', 'en' => 'Nile street']),
            'no_address' => $make('Nothing', null),
            'arabic_only' => $make('Arabic only', ['ar' => 'شارع النيل']),
            'english_only' => $make('English only', ['en' => 'Nile street']),
            'blank_english' => $make('Blank english', ['ar' => 'شارع النيل', 'en' => '   ']),
        ];
    }

    public function test_the_switch_finds_every_branch_still_missing_an_address(): void
    {
        $ids = $this->branches();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.facility-branch.list', ['no_address' => 1]));

        $response->assertOk();
        $listed = collect($response->viewData('page')['props']['facilityBranches']['data'])
            ->pluck('id')
            ->all();

        sort($listed);
        // An address that is only whitespace counts as missing too — there is
        // nothing in it for a reader, whatever the column technically holds.
        $expected = [
            $ids['no_address'],
            $ids['arabic_only'],
            $ids['english_only'],
            $ids['blank_english'],
        ];
        sort($expected);

        $this->assertSame($expected, $listed);
        $this->assertNotContains($ids['complete'], $listed);
    }

    public function test_the_count_rides_on_the_switch_whether_or_not_it_is_on(): void
    {
        $this->branches();

        $response = $this->actingAs($this->admin())->get(route('admin.facility-branch.list'));

        $response->assertOk();
        $this->assertSame(4, $response->viewData('page')['props']['incompleteCounts']['no_address']);
    }

    public function test_without_the_switch_every_branch_is_listed(): void
    {
        $this->branches();

        $response = $this->actingAs($this->admin())->get(route('admin.facility-branch.list'));

        $response->assertOk();
        $this->assertCount(5, $response->viewData('page')['props']['facilityBranches']['data']);
    }

    public function test_the_list_carries_both_languages_of_the_address(): void
    {
        $this->branches();

        $response = $this->actingAs($this->admin())->get(route('admin.facility-branch.list'));

        $addresses = collect($response->viewData('page')['props']['facilityBranches']['data'])
            ->pluck('address');

        // Both spellings travel, so the card can show them and name the one
        // that is missing rather than silently showing the locale's fallback.
        $this->assertTrue($addresses->contains(['ar' => 'شارع النيل', 'en' => 'Nile street']));
        $this->assertTrue($addresses->contains(['ar' => 'شارع النيل']));
    }
}
