<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A branch keeps one entry per phone number, each saying what kind of line it
 * is. Rows written before that — and every spreadsheet import, which has
 * nowhere to say — still arrive as flat strings, so both shapes have to work.
 */
class FacilityBranchPhoneTypesTest extends TestCase
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

    private function facility(): Facility
    {
        $type = FacilityType::create(['name' => ['en' => 'Clinic', 'ar' => 'عيادة']]);

        return Facility::create([
            'name' => ['en' => 'Mytra Labs', 'ar' => 'معامل ميترا'],
            'facility_type_id' => $type->id,
        ]);
    }

    public function test_each_number_is_saved_with_the_type_the_form_chose(): void
    {
        $facility = $this->facility();

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
                'phone' => [
                    ['number' => '0663400006', 'type' => FacilityBranch::PHONE_LANDLINE],
                    ['number' => '01020709993', 'type' => FacilityBranch::PHONE_MOBILE_WHATSAPP],
                ],
            ],
        )->assertOk();

        $branch = FacilityBranch::firstOrFail();

        $this->assertSame([
            ['number' => '0663400006', 'type' => 'landline'],
            ['number' => '01020709993', 'type' => 'phone_whatsapp'],
        ], $branch->phone);
    }

    public function test_a_number_sent_without_a_type_is_typed_from_its_shape(): void
    {
        $facility = $this->facility();

        // What the spreadsheet importer and anything written against the older
        // shape still send.
        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Maadi', 'ar' => 'المعادي'],
                'phone' => ['01020709993', '0233046378'],
            ],
        )->assertOk();

        $this->assertSame([
            ['number' => '01020709993', 'type' => 'phone'],
            ['number' => '0233046378', 'type' => 'landline'],
        ], FacilityBranch::firstOrFail()->phone);
    }

    public function test_an_unknown_type_is_refused_rather_than_stored(): void
    {
        $facility = $this->facility();

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
                'phone' => [['number' => '01020709993', 'type' => 'telegram']],
            ],
        )->assertOk();

        // entries() drops the unusable type back to a guess rather than failing
        // the save — the number is the thing worth keeping.
        $this->assertSame('phone', FacilityBranch::firstOrFail()->phone[0]['type']);
    }

    public function test_a_blank_row_is_dropped_and_an_empty_list_stores_null(): void
    {
        $facility = $this->facility();

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
                'phone' => [
                    ['number' => '01020709993', 'type' => 'phone'],
                    ['number' => '   ', 'type' => 'landline'],
                ],
            ],
        )->assertOk();

        $branch = FacilityBranch::firstOrFail();
        $this->assertCount(1, $branch->phone);

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'id' => $branch->id,
                'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
                'phone' => [],
            ],
        )->assertOk();

        // "No phone" has always been NULL in this column, not "[]".
        $this->assertNull(DB::table('facility_branches')->where('id', $branch->id)->value('phone'));
        $this->assertSame([], $branch->fresh()->phone);
    }

    public function test_a_row_still_holding_the_old_flat_shape_reads_back_typed(): void
    {
        $facility = $this->facility();

        // Written past the model, exactly as it sat before the migration.
        $id = DB::table('facility_branches')->insertGetId([
            'facility_id' => $facility->id,
            'slug' => 'legacy-branch',
            'name' => json_encode(['en' => 'Legacy', 'ar' => 'قديم']),
            'phone' => json_encode(['0663400006', '01020709993']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branch = FacilityBranch::findOrFail($id);

        $this->assertSame([
            ['number' => '0663400006', 'type' => 'landline'],
            ['number' => '01020709993', 'type' => 'phone'],
        ], $branch->phone);
        $this->assertSame(['0663400006', '01020709993'], $branch->phoneNumbers());
    }

    public function test_a_packed_cell_is_split_and_every_number_keeps_the_declared_type(): void
    {
        $facility = $this->facility();

        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
                'phone' => [['number' => '01020709993/01020709994', 'type' => 'whatsapp']],
            ],
        )->assertOk();

        $this->assertSame([
            ['number' => '01020709993', 'type' => 'whatsapp'],
            ['number' => '01020709994', 'type' => 'whatsapp'],
        ], FacilityBranch::firstOrFail()->phone);
    }

    public function test_an_overlong_number_is_reported(): void
    {
        $facility = $this->facility();

        // Long enough that the splitter's glued-numbers rescue does not apply,
        // so it stays one unusable value and has to be rejected.
        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
                'phone' => [['number' => str_repeat('9', 34), 'type' => 'phone']],
            ],
        )->assertStatus(422)
            ->assertJsonValidationErrors('phone.0.number');
    }

    public function test_two_numbers_glued_together_are_split_rather_than_rejected(): void
    {
        $facility = $this->facility();

        // The rescue the splitter has always done for imported cells: peel the
        // 11-digit mobile off the end and keep the rest as the landline.
        $this->actingAs($this->admin())->postJson(
            route('admin.facility.branch.save', $facility->slug),
            [
                'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
                'phone' => [['number' => '0233046378-01210541111', 'type' => 'landline']],
            ],
        )->assertOk();

        $this->assertSame(
            ['0233046378', '01210541111'],
            array_column(FacilityBranch::firstOrFail()->phone, 'number')
        );
    }

    public function test_the_public_api_keeps_flat_numbers_and_gains_the_typed_list(): void
    {
        $facility = $this->facility();

        FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'],
            'phone' => [['number' => '01020709993', 'type' => 'phone_whatsapp']],
        ]);

        $response = $this->getJson(route('api.v1.facilities.show', $facility->slug));

        $response->assertOk();

        // The endpoint answers unwrapped, so read the branch straight off the
        // payload rather than assuming a `data` envelope.
        $branch = $response->json('branches.0') ?? $response->json('data.branches.0');

        $this->assertSame(['01020709993'], $branch['phone']);
        $this->assertSame(
            [['number' => '01020709993', 'type' => 'phone_whatsapp']],
            $branch['phones']
        );
    }
}
