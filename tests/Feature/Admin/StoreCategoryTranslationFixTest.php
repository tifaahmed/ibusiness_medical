<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\StoreCategory;
use App\Models\User;
use App\Services\Ai\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Store categories: the AI fills the missing language (or moves a wrong-language name); nothing is saved on preview. */
class StoreCategoryTranslationFixTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAi(array $answers): void
    {
        $this->mock(GeminiClient::class, fn ($m) => $m->shouldReceive('json')->andReturn($answers));
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        Permission::findOrCreate('manage store categories', 'web');
        Permission::findOrCreate('manage own store categories', 'web');
        $u->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $u;
    }

    public function test_preview_proposes_and_apply_saves_only_what_is_ticked(): void
    {
        $a = StoreCategory::create(['name' => ['ar' => 'صيدليات', 'en' => 'صيدليات']]);
        $ok = StoreCategory::create(['name' => ['ar' => 'بصريات', 'en' => 'Optics']]);
        $this->fakeAi([(string) $a->id => ['ar' => 'صيدليات', 'en' => 'Pharmacies']]);

        $res = $this->actingAs($this->admin())->withoutMiddleware()->postJson(route('admin.store-category.translations.preview'))->assertOk();
        $res->assertJsonCount(1, 'proposals')->assertJsonPath('proposals.0.to.en', 'Pharmacies');
        $this->assertSame('صيدليات', $a->fresh()->getTranslation('name', 'en')); // nothing written yet

        $this->actingAs($this->admin())->withoutMiddleware()
            ->postJson(route('admin.store-category.translations.apply'), ['items' => [['id' => $a->id, 'ar' => 'صيدليات', 'en' => 'Pharmacies'], ['id' => $ok->id, 'ar' => 'بصريات', 'en' => 'بصريات']]])
            ->assertOk()->assertJsonPath('updated', 1)->assertJsonPath('skipped', 1);

        $this->assertSame('Pharmacies', $a->fresh()->getTranslation('name', 'en'));
        $this->assertSame('Optics', $ok->fresh()->getTranslation('name', 'en')); // a wrong-language answer is refused
    }

    public function test_single_fixes_the_two_boxes_as_typed_and_writes_nothing(): void
    {
        $this->fakeAi(['1' => ['ar' => 'مستلزمات طبية', 'en' => 'Medical Supplies']]);

        $this->actingAs($this->admin())->withoutMiddleware()
            ->postJson(route('admin.store-category.translations.single'), ['ar' => '', 'en' => 'Medical Supplies'])
            ->assertOk()->assertExactJson(['ar' => 'مستلزمات طبية', 'en' => 'Medical Supplies']);

        $this->assertDatabaseCount('store_categories', 0);
    }
}
