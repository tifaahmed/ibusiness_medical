<?php

namespace Tests\Feature;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\User;
use App\Services\Otp\MemberOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A member's fixed login code: super admin only, stored hashed, expires by itself, audited. */
class MemberFixedLoginCodeTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $user = User::factory()->create(['phone' => '01044171461']);
        Membership::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        return $user;
    }

    private function admin(string $role): User
    {
        $admin = User::factory()->create();
        Role::findOrCreate($role, 'web');
        $admin->assignRole($role);

        return $admin;
    }

    /** @test */
    public function only_the_super_admin_can_set_it_and_it_is_stored_hashed_with_an_audit_row(): void
    {
        $member = $this->member();

        $this->actingAs($this->admin(UserRoleEnum::ADMIN))
            ->put(route('admin.user.membership.otp.member', $member), ['fixed_code' => '1234'])->assertForbidden();
        $this->assertNull($member->fresh()->otp_fixed_code_hash);

        $this->actingAs($this->admin(UserRoleEnum::SUPER_ADMIN))
            ->put(route('admin.user.membership.otp.member', $member), ['fixed_code' => '1234', 'expires_at' => now()->addDay()->toDateTimeString()])
            ->assertSessionHasNoErrors();

        $fresh = $member->fresh();
        $this->assertNotSame('1234', $fresh->otp_fixed_code_hash);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('1234', $fresh->otp_fixed_code_hash));
        $this->assertNull($fresh->otp_fixed_code);
        $this->assertTrue($fresh->hasActiveFixedCode());
        $this->assertDatabaseHas('member_logs', ['membership_id' => $member->memberships()->first()->id, 'action' => 'updated']);
    }

    /** @test */
    public function the_fixed_code_verifies_and_stops_working_after_it_expires(): void
    {
        $member = $this->member();
        $member->forceFill([
            'otp_fixed_code_hash' => \Illuminate\Support\Facades\Hash::make('1234'),
            'otp_fixed_code_length' => 4,
            'otp_fixed_code_expires_at' => now()->addHour(),
        ])->save();

        $otp = app(MemberOtp::class);
        $issue = $otp->issue('01044171461');
        $this->assertSame('fixed', $issue['delivery']);
        $this->assertTrue($otp->verify('01044171461', '1234')['verified']);

        $member->forceFill(['otp_fixed_code_expires_at' => now()->subMinute()])->save();
        $this->assertFalse($member->fresh()->hasActiveFixedCode());
    }

    /** @test */
    public function the_app_is_told_a_fixed_code_exists_and_until_when_but_never_the_code(): void
    {
        $member = $this->member();
        $until = now()->addDays(3)->startOfSecond();
        $member->forceFill([
            'otp_fixed_code_hash' => \Illuminate\Support\Facades\Hash::make('4321'),
            'otp_fixed_code_length' => 4,
            'otp_fixed_code_expires_at' => $until,
        ])->save();

        $issue = app(MemberOtp::class)->issue('01044171461');
        $this->assertTrue($issue['member_fixed']);
        $this->assertSame($until->toIso8601String(), $issue['fixed_until']);
        $this->assertStringNotContainsString('4321', json_encode($issue));

        // Someone else's phone is a plain code: nothing about a fixed code.
        $other = app(MemberOtp::class)->issue('01099999999');
        $this->assertFalse($other['member_fixed']);
        $this->assertNull($other['fixed_until']);
    }
}
