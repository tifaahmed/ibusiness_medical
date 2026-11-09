<?php

namespace Tests\Feature;

use App\Enums\User\UserRoleEnum;
use App\Models\User;
use App\Support\OtpSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The login SMS wording: short by default, editable from the dashboard, must carry :code. */
class OtpSmsTextTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        Permission::findOrCreate('manage settings', 'web');
        $admin->assignRole(UserRoleEnum::SUPER_ADMIN);
        $admin->givePermissionTo('manage settings');

        return $admin;
    }

    public function test_default_text_is_short_with_no_brand_or_verification(): void
    {
        $this->assertSame('Your code is 1223', OtpSettings::message('1223'));
    }

    public function test_admin_changes_the_text_and_it_is_used(): void
    {
        $this->actingAs($this->admin())->withoutMiddleware()
            ->put(route('admin.user.membership.otp.site'), ['sms_enabled' => true, 'fixed_code' => '1234', 'message' => 'Code: :code'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Code: 5555', OtpSettings::message('5555'));
    }

    public function test_text_without_code_placeholder_is_refused_and_empty_resets(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withoutMiddleware()
            ->put(route('admin.user.membership.otp.site'), ['sms_enabled' => true, 'message' => 'hello'])
            ->assertSessionHasErrors('message');

        $this->flushSession();
        $this->put(route('admin.user.membership.otp.site'), ['sms_enabled' => true, 'message' => null])->assertSessionHasNoErrors();
        $this->assertSame('Your code is 1223', OtpSettings::message('1223'));
    }
}
