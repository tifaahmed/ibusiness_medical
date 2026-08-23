<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A tag's name is translatable, like every other name in the catalogue: the
 * storefront resolves it for the language its visitor is reading, so the
 * dashboard has to write and hand back both languages rather than one.
 */
class TagTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage services', 'web'));
        $user->assignRole($role);

        return $user;
    }

    public function test_a_tag_is_created_with_a_name_in_each_language(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.tag.store'), [
                'name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا'],
                'icon' => '🔥',
                'color' => '#ff0000',
            ])
            ->assertRedirect(route('admin.tag.list'));

        $tag = Tag::query()->firstOrFail();

        $this->assertSame('Best seller', $tag->getTranslation('name', 'en'));
        $this->assertSame('الأكثر مبيعًا', $tag->getTranslation('name', 'ar'));
        $this->assertSame('🔥', $tag->icon);
    }

    public function test_a_name_missing_a_language_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.tag.store'), [
                'name' => ['en' => 'Best seller', 'ar' => ''],
            ])
            ->assertSessionHasErrors('name.ar');

        $this->assertSame(0, Tag::query()->count());
    }

    public function test_the_edit_page_receives_every_translation(): void
    {
        $tag = Tag::create([
            'name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا'],
            'icon' => '🔥',
            'color' => '#ff0000',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.tag.edit', $tag->id))
            ->assertOk();

        $name = $response->viewData('page')['props']['tag']['name'];

        $this->assertSame('Best seller', $name['en']);
        $this->assertSame('الأكثر مبيعًا', $name['ar']);
    }

    public function test_a_tag_is_updated_in_both_languages(): void
    {
        $tag = Tag::create([
            'name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا'],
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.tag.update', $tag->id), [
                'name' => ['en' => 'Top rated', 'ar' => 'الأعلى تقييمًا'],
                'icon' => '💯',
                'color' => '#f59e0b',
            ])
            ->assertRedirect(route('admin.tag.list'));

        $tag->refresh();

        $this->assertSame('Top rated', $tag->getTranslation('name', 'en'));
        $this->assertSame('الأعلى تقييمًا', $tag->getTranslation('name', 'ar'));
        $this->assertSame('💯', $tag->icon);
    }

    /**
     * Both names are listed, so both have to be searchable — an admin looking
     * for a tag types it in whichever language they are working in.
     */
    public function test_the_list_is_searchable_in_either_language(): void
    {
        Tag::create(['name' => ['en' => 'Best seller', 'ar' => 'الأكثر مبيعًا']]);
        Tag::create(['name' => ['en' => 'Sterile', 'ar' => 'معقم']]);

        $admin = $this->admin();

        $arabic = $this->actingAs($admin)
            ->get(route('admin.tag.list', ['search' => 'الأكثر']))
            ->assertOk();

        $this->assertCount(1, $arabic->viewData('page')['props']['tags']['data']);

        $english = $this->actingAs($admin)
            ->get(route('admin.tag.list', ['search' => 'Sterile']))
            ->assertOk();

        $rows = $english->viewData('page')['props']['tags']['data'];

        $this->assertCount(1, $rows);
        $this->assertSame('معقم', $rows[0]['name']['ar']);
    }
}
