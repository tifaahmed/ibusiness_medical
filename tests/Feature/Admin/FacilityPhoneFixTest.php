<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\User;
use App\Support\PhoneRepair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Branch numbers have three shapes and no others: an 11-digit mobile beginning
 * "01", a 10-digit landline with its area code, and a hotline — shorter than a
 * landline and dialled exactly as it stands.
 */
class FacilityPhoneFixTest extends TestCase
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

    private function branch(array $phones): FacilityBranch
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $facility = Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);

        return FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
            'phone' => $phones,
        ]);
    }

    /**
     * A row as it was written before phones were split and typed on save —
     * several numbers in one cell, which is what the older imports left behind
     * and the only way a packed cell can still exist.
     *
     * @param  array<int, string>  $phones
     */
    private function legacyBranch(array $phones): FacilityBranch
    {
        $branch = $this->branch(['01000000000']);

        DB::table('facility_branches')
            ->where('id', $branch->id)
            ->update(['phone' => json_encode($phones, JSON_UNESCAPED_UNICODE)]);

        return $branch->fresh();
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function numbers(): array
    {
        return [
            'area code and mobile in one cell' => ['066 3222328 / 01208999581', ['0663222328', '01208999581']],
            'cairo landline' => ['0212345678', ['0212345678']],
            'landline already right' => ['0663222328', ['0663222328']],
            'mobile left alone' => ['01208999581', ['01208999581']],
            'international mobile' => ['+201208999581', ['01208999581']],
            'international landline keeps its zero' => ['+20 66 3222328', ['0663222328']],
            'double-zero country code' => ['00201023307060', ['01023307060']],
            'arabic-indic digits' => ['٠١٢٠٨٩٩٩٥٨١', ['01208999581']],
            // Short national numbers stand as they are: there is no area code
            // in front of one, so trimming it would destroy the number.
            'hotline left alone' => ['16064', ['16064']],
            'five-digit hotline' => ['19011', ['19011']],
            'hotline packed with a mobile' => ['16064 / 01208999581', ['16064', '01208999581']],
            'three numbers in one cell' => [
                '066 3400006 / 01023307060 / 01208999584',
                ['0663400006', '01023307060', '01208999584'],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('numbers')]
    public function test_a_number_is_repaired_to_its_proper_shape(string $stored, array $expected): void
    {
        $this->assertSame($expected, array_column(PhoneRepair::repair([$stored])['suggested'], 'number'));
    }

    public function test_a_number_too_short_to_guess_is_left_for_a_human(): void
    {
        $repair = PhoneRepair::repair(['19011', '0120899958']);

        $this->assertTrue($repair['needs_review']);
        // Nothing is dropped: what could not be repaired is still there.
        $this->assertSame(['19011', '0120899958'], array_column($repair['suggested'], 'number'));
    }

    public function test_the_page_lists_only_the_branches_that_need_fixing(): void
    {
        $wrong = $this->legacyBranch(['066 3222328 / 01208999581']);
        $right = FacilityBranch::create([
            'facility_id' => $wrong->facility_id,
            'name' => ['en' => 'Port Said', 'ar' => 'بورسعيد'],
            'phone' => ['01050407150'],
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.facility.phones.page'));

        $response->assertOk();
        $problems = $response->viewData('page')['props']['problems'];

        $this->assertSame(1, $problems['meta']['total']);
        $this->assertSame($wrong->id, $problems['data'][0]['branch_id']);
        $this->assertSame(['066 3222328 / 01208999581'], $problems['data'][0]['current']);
        $this->assertSame(['0663222328', '01208999581'], array_column($problems['data'][0]['suggested'], 'number'));
        $this->assertNotContains($right->id, array_column($problems['data'], 'branch_id'));
    }

    public function test_confirming_a_row_writes_the_numbers_it_was_sent(): void
    {
        $branch = $this->legacyBranch(['066 3222328 / 01208999581']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.phones.fix'), [
                'branch_id' => $branch->id,
                'phones' => [
                    ['number' => '0663222328', 'type' => 'landline'],
                    ['number' => '01208999581', 'type' => 'phone'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('phones.0.number', '0663222328')
            ->assertJsonPath('phones.1.number', '01208999581');

        $this->assertSame(['0663222328', '01208999581'], $branch->fresh()->phoneNumbers());
    }

    public function test_the_admin_can_edit_the_suggestion_before_confirming(): void
    {
        $branch = $this->branch(['19011']);

        // The rules could not repair this one, so the admin retyped it.
        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.phones.fix'), [
                'branch_id' => $branch->id,
                'phones' => [
                    ['number' => '0663222328', 'type' => 'landline'],
                    ['number' => '01208999581', 'type' => 'phone'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('applied', true);

        $this->assertSame(['0663222328', '01208999581'], $branch->fresh()->phoneNumbers());
    }

    public function test_an_edited_number_is_still_split_and_folded(): void
    {
        $branch = $this->branch(['19011']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.phones.fix'), [
                'branch_id' => $branch->id,
                'phones' => [['number' => '0663222328 / ٠١٢٠٨٩٩٩٥٨١', 'type' => 'landline']],
            ])
            ->assertOk()
            ->assertJsonPath('phones.0.number', '0663222328')
            ->assertJsonPath('phones.1.number', '01208999581');
    }

    public function test_confirming_the_numbers_already_stored_changes_nothing(): void
    {
        $branch = $this->branch([['number' => '01050407150', 'type' => 'phone']]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.phones.fix'), [
                'branch_id' => $branch->id,
                'phones' => [['number' => '01050407150', 'type' => 'phone']],
            ])
            ->assertOk()
            ->assertJsonPath('applied', false);

        $this->assertSame(['01050407150'], $branch->fresh()->phoneNumbers());
    }

    public function test_the_kind_filter_lists_only_that_kind_of_problem(): void
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $facility = Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);

        $landline = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Damietta', 'ar' => 'دمياط'],
            'phone' => ['0212345678'],
        ]);
        // Stored the way an international export leaves a landline: the country
        // code in front and the national leading zero dropped.
        DB::table('facility_branches')->where('id', $landline->id)->update([
            'phone' => json_encode(['00202 12345678']),
        ]);
        $mobiles = FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Port Said', 'ar' => 'بورسعيد'],
            'phone' => ['01208999510'],
        ]);
        // Written the way the old imports left it: two numbers in one cell.
        DB::table('facility_branches')->where('id', $mobiles->id)->update([
            'phone' => json_encode(['01208999510 / 01066689760']),
        ]);

        $admin = $this->admin();

        $listed = fn (string $kind) => array_column(
            $this->actingAs($admin)
                ->get(route('admin.facility.phones.page', ['kind' => $kind]))
                ->assertOk()
                ->viewData('page')['props']['problems']['data'],
            'branch_id',
        );

        $this->assertEqualsCanonicalizing([$landline->id, $mobiles->id], $listed('all'));
        $this->assertSame([$mobiles->id], $listed('mobile'));
        $this->assertSame([$landline->id], $listed('landline'));
    }

    public function test_a_kind_that_is_not_being_worked_on_is_left_alone(): void
    {
        $branch = $this->legacyBranch(['066 3222328 / 01208999581']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.facility.phones.page', ['kind' => 'mobile']));

        $row = $response->assertOk()->viewData('page')['props']['problems']['data'][0];

        // The mobile is pulled out of the packed cell; the landline keeps its
        // area code until the landline pass is run.
        $this->assertSame(['0663222328', '01208999581'], array_column($row['suggested'], 'number'));
        $this->assertSame($branch->id, $row['branch_id']);
    }

    public function test_the_page_paginates(): void
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);
        $facility = Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);

        foreach (range(1, 7) as $i) {
            $branch = FacilityBranch::create([
                'facility_id' => $facility->id,
                'name' => ['en' => "Branch {$i}", 'ar' => "فرع {$i}"],
                'phone' => ['066 322232'.$i],
            ]);
            // Two numbers sharing one cell — wrong however the shapes are
            // defined, which is what keeps this test about the paginator.
            DB::table('facility_branches')->where('id', $branch->id)->update([
                'phone' => json_encode(['066 322232'.$i.' / 0120899958'.$i]),
            ]);
        }

        $response = $this->actingAs($this->admin())
            ->get(route('admin.facility.phones.page', ['per_page' => 5, 'page' => 2]));

        $problems = $response->assertOk()->viewData('page')['props']['problems'];

        $this->assertSame(7, $problems['meta']['total']);
        $this->assertSame(2, $problems['meta']['current_page']);
        $this->assertCount(2, $problems['data']);
    }

    public function test_a_number_filed_as_the_wrong_kind_of_line_is_flagged_and_refiled(): void
    {
        // A hotline stored as a landline: the digits are already right, so
        // nothing but the type is wrong — and the type predates hotlines
        // entirely, which is why so many rows carry it.
        $repair = PhoneRepair::repair([['number' => '16064', 'type' => 'landline']]);

        $this->assertTrue($repair['has_problem']);
        $entry = $repair['entries'][0];
        $this->assertSame('hotline', $entry['kind']);
        $this->assertSame('landline', $entry['type']);
        $this->assertSame('hotline', $entry['suggested_type']);
        $this->assertTrue($entry['type_changed']);
        // Confirming the row saves the corrected type with the untouched number.
        $this->assertSame([['number' => '16064', 'type' => 'hotline']], $repair['suggested']);
    }

    public function test_a_number_reachable_on_whatsapp_is_never_refiled_by_its_digits(): void
    {
        // WhatsApp says how a number is reached, which no count of digits can
        // settle — only a person knows it, so it is left exactly as filed.
        $repair = PhoneRepair::repair([['number' => '01208999581', 'type' => 'whatsapp']]);

        $this->assertFalse($repair['has_problem']);
        $this->assertFalse($repair['entries'][0]['type_changed']);
        $this->assertSame('whatsapp', $repair['entries'][0]['suggested_type']);
    }

    public function test_confirming_a_row_can_change_the_type_alone(): void
    {
        $branch = $this->branch([['number' => '16064', 'type' => 'landline']]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.facility.phones.fix'), [
                'branch_id' => $branch->id,
                'phones' => [['number' => '16064', 'type' => 'hotline']],
            ])
            ->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('phones.0.type', 'hotline');

        $this->assertSame([['number' => '16064', 'type' => 'hotline']], $branch->fresh()->phone);
    }
}
