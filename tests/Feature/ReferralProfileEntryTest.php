<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Referrals;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Entering a referral code from the profile: previewed, once for ever, no expiry, individuals only, 1 EGP to the referrer. */
class ReferralProfileEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSettings::put('referral_signup_bonus', 1, Setting::TYPE_NUMBER);
        SiteSettings::forget();
    }

    private function member(string $number, string $kind = 'individual', ?string $created = null): array
    {
        $user = User::factory()->create(['phone' => '010'.random_int(10000000, 99999999), 'phone_verified_at' => now(), 'created_at' => $created ?? now()]);
        $card = Membership::factory()->create(['user_id' => $user->id, 'membership_number' => $number, 'member_kind' => $kind, 'is_active' => true, 'is_visible' => true]);

        return [$user, $card];
    }

    public function test_preview_writes_nothing_and_names_the_owner(): void
    {
        [$referrer] = $this->member('111111');
        [$me] = $this->member('222222');
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/referral/preview', ['number' => '111111'])
            ->assertOk()->assertJsonPath('data.number', '111111');

        $this->assertDatabaseCount('referrals', 0);
        $this->assertSame(0.0, WalletTransaction::balanceOf($referrer->id));
    }

    public function test_an_old_account_can_enter_a_code_and_the_referrer_gets_the_bonus_once(): void
    {
        [$referrer] = $this->member('111111');
        [$me] = $this->member('222222', 'individual', now()->subYear()->toDateTimeString());
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/referral/claim', ['number' => '111111', 'source' => 'card'])->assertCreated();
        $this->assertSame(1.0, WalletTransaction::balanceOf($referrer->id));

        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertStatus(422)->assertJsonPath('reason', 'already_referred');
        $this->assertSame(1.0, WalletTransaction::balanceOf($referrer->id));
    }

    public function test_company_members_cannot_enter_a_code(): void
    {
        $this->member('111111');
        [$me] = $this->member('222222', 'company');
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/referral/preview', ['number' => '111111'])->assertStatus(422)->assertJsonPath('reason', 'company');
        $this->postJson('/api/v1/referral/claim', ['number' => '111111'])->assertStatus(422)->assertJsonPath('reason', 'company');
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_an_admin_can_enter_a_code_for_a_member_after_previewing_it(): void
    {
        [$referrer] = $this->member('111111');
        $me = User::factory()->create(['phone_verified_at' => null]);
        Membership::factory()->create(['user_id' => $me->id, 'membership_number' => '333333', 'member_kind' => 'individual', 'is_active' => true, 'is_visible' => true]);
        $admin = User::factory()->create();
        \Spatie\Permission\Models\Permission::findOrCreate('manage memberships', 'web');
        $admin->givePermissionTo('manage memberships');

        $this->actingAs($admin)->getJson("/admin/user/membership/{$me->id}/referral?number=111111")->assertOk()->assertJsonPath('owner.number', '111111');
        $this->assertDatabaseCount('referrals', 0);

        $this->actingAs($admin)->postJson("/admin/user/membership/{$me->id}/referral", ['number' => '111111'])->assertStatus(422); // not confirmed
        $this->actingAs($admin)->postJson("/admin/user/membership/{$me->id}/referral", ['number' => '111111', 'confirmed' => true])->assertCreated();
        $this->assertSame(1.0, WalletTransaction::balanceOf($referrer->id));

        $this->actingAs($admin)->postJson("/admin/user/membership/{$me->id}/referral", ['number' => '111111', 'confirmed' => true])->assertStatus(422)->assertJsonPath('reason', 'already_referred');
        $this->assertSame(1.0, WalletTransaction::balanceOf($referrer->id));
    }
}
