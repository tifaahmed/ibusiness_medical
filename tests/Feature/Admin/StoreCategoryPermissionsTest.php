<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserPermissionEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Store-category permissions exist after migrating (they once did not, and saving an admin that ticked one was a 500). */
class StoreCategoryPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_permissions_exist_so_an_admin_can_be_given_them(): void
    {
        foreach ([UserPermissionEnum::MANAGE_STORE_CATEGORIES, UserPermissionEnum::MANAGE_OWN_STORE_CATEGORIES, UserPermissionEnum::VIEW_STORE_CATEGORIES] as $name) {
            $this->assertNotNull(Permission::query()->where('name', $name)->where('guard_name', 'web')->first(), "$name is missing");
        }
    }
}
