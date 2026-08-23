<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Product;
use App\Models\ProductGallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductDescriptionImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage products', 'web'));
        $user->assignRole($role);

        return $user;
    }

    public function test_editor_upload_stores_the_file_and_returns_its_url(): void
    {
        Storage::fake('public');
        $user = $this->admin();

        $response = $this->actingAs($user)->post(route('admin.product.editor-image'), [
            'image' => UploadedFile::fake()->image('inline.jpg'),
        ]);

        $response->assertOk();

        $path = $response->json('path');

        $this->assertStringStartsWith(ProductGallery::EDITOR_DIRECTORY.'/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $response->json('url'));

        // Nothing is attached until the product form is saved.
        $this->assertDatabaseCount('product_gallery', 0);
    }

    public function test_uploaded_editor_image_joins_the_gallery_on_update(): void
    {
        Storage::fake('public');
        $user = $this->admin();

        $product = Product::create([
            'name' => ['en' => 'Scale', 'ar' => 'ميزان'],
            'slug' => 'digital-scale',
            'created_by' => $user->id,
        ]);

        $path = $this->actingAs($user)->post(route('admin.product.editor-image'), [
            'image' => UploadedFile::fake()->image('inline.jpg'),
        ])->json('path');

        $url = Storage::disk('public')->url($path);

        $this->actingAs($user)->put(route('admin.product.update', $product->slug), [
            'name' => ['en' => 'Scale', 'ar' => 'ميزان'],
            'description' => ['ar' => '<p>وصف</p><img src="'.$url.'">', 'en' => ''],
            'editor_gallery_paths' => [$path],
        ])->assertRedirect(route('admin.product.list'));

        $this->assertDatabaseHas('product_gallery', [
            'product_id' => $product->id,
            'image_path' => $path,
        ]);
    }

    public function test_a_second_save_does_not_duplicate_the_gallery_row(): void
    {
        Storage::fake('public');
        $user = $this->admin();

        $product = Product::create([
            'name' => ['en' => 'Scale', 'ar' => 'ميزان'],
            'slug' => 'digital-scale-2',
            'created_by' => $user->id,
        ]);

        $path = $this->actingAs($user)->post(route('admin.product.editor-image'), [
            'image' => UploadedFile::fake()->image('inline.jpg'),
        ])->json('path');

        $payload = [
            'name' => ['en' => 'Scale', 'ar' => 'ميزان'],
            'editor_gallery_paths' => [$path],
        ];

        $this->actingAs($user)->put(route('admin.product.update', $product->slug), $payload);
        $this->actingAs($user)->put(route('admin.product.update', $product->slug), $payload);

        $this->assertSame(1, $product->galleries()->count());
    }

    public function test_a_forged_path_is_ignored(): void
    {
        Storage::fake('public');
        $user = $this->admin();

        $product = Product::create([
            'name' => ['en' => 'Scale', 'ar' => 'ميزان'],
            'slug' => 'digital-scale-3',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->put(route('admin.product.update', $product->slug), [
            'name' => ['en' => 'Scale', 'ar' => 'ميزان'],
            'editor_gallery_paths' => ['products/large/secret.jpg', '../../.env'],
        ]);

        $this->assertSame(0, $product->galleries()->count());
    }

    public function test_after_save_intent_picks_the_redirect(): void
    {
        $user = $this->admin();

        $product = Product::create([
            'name' => ['en' => 'Scale', 'ar' => 'ميزان'],
            'slug' => 'digital-scale-4',
            'created_by' => $user->id,
        ]);

        $payload = ['name' => ['en' => 'Scale', 'ar' => 'ميزان']];

        // Saving re-slugs the product from its name, so each request uses the
        // slug the previous one left behind — which is what the browser does too.
        $this->actingAs($user)
            ->put(route('admin.product.update', $product->slug), $payload + ['after_save' => 'stay'])
            ->assertRedirect(route('admin.product.edit', $product->fresh()->slug));

        $this->actingAs($user)
            ->put(route('admin.product.update', $product->fresh()->slug), $payload + ['after_save' => 'show'])
            ->assertRedirect(route('admin.product.show', $product->fresh()->slug));

        $this->actingAs($user)
            ->put(route('admin.product.update', $product->fresh()->slug), $payload)
            ->assertRedirect(route('admin.product.list'));
    }
}
