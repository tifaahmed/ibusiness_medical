<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductShowTranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_receives_both_locales(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage products', 'web'));
        $user->assignRole($role);

        $product = Product::create([
            'name' => ['en' => 'English Name', 'ar' => 'اسم عربي'],
            'short_subject' => ['en' => 'Short EN', 'ar' => 'قصير عربي'],
            'description' => ['en' => 'Long EN', 'ar' => 'وصف عربي'],
            'slug' => 'both-locales',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.product.show', $product->slug));
        $response->assertOk();

        $productProp = $response->viewData('page')['props']['product'];

        $this->assertSame('اسم عربي', $productProp['name']['ar']);
        $this->assertSame('English Name', $productProp['name']['en']);
        $this->assertSame('قصير عربي', $productProp['short_subject']['ar']);
        $this->assertSame('Short EN', $productProp['short_subject']['en']);
        $this->assertSame('وصف عربي', $productProp['description']['ar']);
        $this->assertSame('Long EN', $productProp['description']['en']);
    }
}
