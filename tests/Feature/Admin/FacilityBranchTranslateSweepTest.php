<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Fix translations with AI" on the branch list: the Arabic and English of every
 * branch name and address that is missing, in the wrong language, or a copy of
 * the other side.
 *
 * The provider is always faked. These assert which branches are queued, that name
 * and address are held to the same rule, and that only a clean answer is ever
 * written — a reply that is still wrong must leave the branch exactly as it was.
 */
class FacilityBranchTranslateSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-key']);
    }

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

    private function branch(Facility $facility, array $name, ?array $address): FacilityBranch
    {
        return FacilityBranch::create([
            'facility_id' => $facility->id,
            'name' => $name,
            'address' => $address,
        ]);
    }

    private function fakeAi(array $payload, int $status = 200): void
    {
        Http::fake([
            '*' => Http::response(
                $status === 200
                    ? ['candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]]]
                    : ['error' => ['message' => 'quota']],
                $status,
            ),
        ]);
    }

    public function test_the_work_list_holds_only_branches_with_a_name_or_address_to_fix(): void
    {
        $facility = $this->facility();

        $right = $this->branch($facility, ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'], ['ar' => 'شارع النيل', 'en' => 'Nile Street']);
        $noEnglishName = $this->branch($facility, ['ar' => 'فرع الهرم', 'en' => ''], ['ar' => 'شارع الهرم', 'en' => 'Haram Street']);
        // Same rule for address as for name: a good name does not excuse a bad address.
        $arabicInEnglishAddress = $this->branch($facility, ['ar' => 'فرع الدقي', 'en' => 'Dokki Branch'], ['ar' => 'شارع التحرير', 'en' => 'شارع التحرير']);
        $noAddress = $this->branch($facility, ['ar' => 'فرع مدينة نصر', 'en' => 'Nasr City Branch'], null);
        $latinInArabicBox = $this->branch($facility, ['ar' => 'Zamalek Branch', 'en' => 'Zamalek Branch'], ['ar' => 'شارع 26 يوليو', 'en' => '26 July Street']);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.translate.bulk.begin'))
            ->assertOk();

        $ids = collect($response->json('branches'))->pluck('id')->all();

        $this->assertContains($noEnglishName->id, $ids);
        $this->assertContains($arabicInEnglishAddress->id, $ids);
        $this->assertContains($latinInArabicBox->id, $ids);
        // Nothing in either language of the address: nothing to translate from.
        $this->assertNotContains($noAddress->id, $ids);
        $this->assertNotContains($right->id, $ids);
        $this->assertSame(3, $response->json('total'));
    }

    public function test_a_step_fixes_both_languages_logs_it_and_keeps_the_slug(): void
    {
        $facility = $this->facility();
        $branch = $this->branch($facility, ['ar' => 'فرع الهرم', 'en' => ''], ['ar' => 'شارع الهرم بجوار المحطة', 'en' => 'شارع الهرم بجوار المحطة']);
        $slug = $branch->slug;

        $this->fakeAi([
            '0' => ['ar' => 'فرع الهرم', 'en' => 'Haram Branch'],
            '1' => ['ar' => 'شارع الهرم بجوار المحطة', 'en' => 'Haram Street, next to the station'],
        ]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.translate.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk();

        $this->assertSame('ok', $response->json('results.0.state'));
        $this->assertFalse($response->json('rate_limited'));

        $branch->refresh();
        $this->assertSame('Haram Branch', $branch->getTranslation('name', 'en'));
        $this->assertSame('فرع الهرم', $branch->getTranslation('name', 'ar'));
        $this->assertSame('Haram Street, next to the station', $branch->getTranslation('address', 'en'));
        // Links to the branch keep working: a translation is not worth a new address.
        $this->assertSame($slug, $branch->slug);

        $log = FacilityBranchLog::where('facility_branch_id', $branch->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('ai_translate', $log->new_values['source']);
    }

    public function test_an_answer_still_in_the_wrong_language_writes_nothing(): void
    {
        $facility = $this->facility();
        $branch = $this->branch($facility, ['ar' => 'فرع الهرم', 'en' => ''], ['ar' => 'شارع الهرم', 'en' => 'Haram Street']);

        // The model handed Arabic back in the English box.
        $this->fakeAi(['0' => ['ar' => 'فرع الهرم', 'en' => 'فرع الهرم']]);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.translate.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk();

        $this->assertSame('error', $response->json('results.0.state'));

        $branch->refresh();
        $this->assertSame('', $branch->getTranslation('name', 'en', false));
        $this->assertSame(0, FacilityBranchLog::where('facility_branch_id', $branch->id)->count());
    }

    public function test_a_branch_that_needs_nothing_is_skipped_without_calling_the_ai(): void
    {
        $facility = $this->facility();
        $branch = $this->branch($facility, ['ar' => 'فرع المعادي', 'en' => 'Maadi Branch'], ['ar' => 'شارع النيل', 'en' => 'Nile Street']);

        Http::fake();

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.translate.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk();

        $this->assertSame('skip', $response->json('results.0.state'));
        Http::assertNothingSent();
    }

    public function test_a_spent_quota_hands_the_slice_back_to_be_retried(): void
    {
        $facility = $this->facility();
        $branch = $this->branch($facility, ['ar' => 'فرع الهرم', 'en' => ''], ['ar' => 'شارع الهرم', 'en' => 'Haram Street']);

        $this->fakeAi([], 429);

        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.facility-branch.translate.bulk.step'), ['ids' => [$branch->id]])
            ->assertOk();

        $this->assertTrue($response->json('rate_limited'));
        $this->assertSame('', $branch->refresh()->getTranslation('name', 'en', false));
    }

    public function test_it_is_refused_without_the_branch_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.facility-branch.translate.bulk.begin'))
            ->assertForbidden();
    }
}
