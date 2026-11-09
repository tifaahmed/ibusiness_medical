<?php

namespace Tests\Feature;

use App\Enums\User\UserRoleEnum;
use App\Models\User;
use App\Services\Otp\MemberOtp;
use App\Support\OtpMasterCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The master login code: opens the login next to the real code, super admin only, hashed, audited, expires. */
class MasterLoginCodeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role): User
    {
        $admin = User::factory()->create();
        Role::findOrCreate($role, 'web');
        Permission::findOrCreate('manage settings', 'web');
        $admin->assignRole($role);
        $admin->givePermissionTo('manage settings');

        return $admin;
    }

    private function enable(string $code = '9999', ?string $expires = null): void
    {
        $this->actingAs($this->admin(UserRoleEnum::SUPER_ADMIN))
            ->put(route('admin.user.membership.otp.master'), ['enabled' => true, 'code' => $code, 'expires_at' => $expires])
            ->assertSessionHasNoErrors();
    }

    /** @test */
    public function only_the_super_admin_can_set_it_and_it_is_stored_hashed_and_audited(): void
    {
        $this->actingAs($this->admin(UserRoleEnum::ADMIN))
            ->put(route('admin.user.membership.otp.master'), ['enabled' => true, 'code' => '9999'])->assertForbidden();
        $this->assertFalse(OtpMasterCode::active());

        $this->enable();

        $this->assertTrue(OtpMasterCode::active());
        $this->assertNotSame('9999', \App\Support\SiteSettings::get(OtpMasterCode::HASH));
        $this->assertDatabaseHas('otp_master_code_logs', ['action' => 'changed']);
    }

    /** @test */
    public function both_the_real_code_and_the_master_code_sign_in_and_it_is_off_by_default(): void
    {
        $otp = app(MemberOtp::class);
        $phone = '01044171461';

        $this->assertFalse($this->verifyWith($otp, $phone, '9999')['verified']);

        $this->enable();

        $this->assertTrue($this->verifyWith($otp, $phone, '9999')['verified']);
        $this->assertDatabaseHas('otp_master_code_logs', ['action' => 'used']);

        /* The real code still works, and a wrong one still fails. */
        Cache::put('x', 1);
        $issue = $otp->issue($phone);
        $this->assertNull($issue['error']);
        $this->assertFalse($otp->verify($phone, '0000')['verified']);
    }

    /** @test */
    public function the_master_code_never_marks_a_phone_verified_and_expires_by_itself(): void
    {
        $otp = app(MemberOtp::class);
        $phone = '01044171461';
        $this->enable('9999', now()->addHour()->toDateTimeString());

        $this->assertFalse($this->verifyWith($otp, $phone, '9999')['sms']);

        $this->travel(2)->hours();
        $this->assertFalse(OtpMasterCode::active());
        $this->assertFalse($this->verifyWith($otp, $phone, '9999')['verified']);
    }

    /** @test */
    public function turning_it_off_closes_it_and_a_wrong_length_is_refused(): void
    {
        $this->enable();
        $this->actingAs($this->admin(UserRoleEnum::SUPER_ADMIN))
            ->put(route('admin.user.membership.otp.master'), ['enabled' => true, 'code' => '123456'])->assertSessionHasErrors('code');
        $this->put(route('admin.user.membership.otp.master'), ['enabled' => false])->assertSessionHasNoErrors();

        $this->assertFalse(OtpMasterCode::active());
        $this->assertDatabaseHas('otp_master_code_logs', ['action' => 'disabled']);
    }

    private function verifyWith(MemberOtp $otp, string $phone, string $code): array
    {
        $otp->issue($phone);

        return $otp->verify($phone, $code);
    }
}
