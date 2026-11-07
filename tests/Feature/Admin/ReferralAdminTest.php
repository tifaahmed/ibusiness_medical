<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** /admin/referrals: who brought whom, whether they paid, and what each side got — with filters and totals. */
class ReferralAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_referrals_with_status_amounts_filters_and_totals(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));
        $boss = User::factory()->create(['name' => 'Referrer']);
        Membership::factory()->create(['user_id' => $boss->id, 'membership_number' => '111111']);
        $paid = User::factory()->create(['name' => 'Paid One']);
        $waiting = User::factory()->create(['name' => 'Waiting One']);

        $base = ['referrer_user_id' => $boss->id, 'referrer_number' => '111111', 'discount_amount' => 50];
        $r1 = Referral::create($base + ['referred_user_id' => $paid->id, 'source' => 'link', 'trial_started_at' => now(), 'trial_rewarded_at' => now(), 'trial_bonus' => 10, 'subscribed_at' => now()]);
        Referral::create($base + ['referred_user_id' => $waiting->id, 'source' => 'card']);
        $reward = ['referral_id' => $r1->id, 'referrer_user_id' => $boss->id, 'referred_user_id' => $paid->id];
        ReferralReward::create($reward + ['kind' => 'trial', 'reward_key' => 'trial:'.$r1->id, 'amount' => 10]);
        ReferralReward::create($reward + ['kind' => 'commission', 'reward_key' => 'commission:1', 'amount' => 60, 'base_amount' => 600, 'rate' => 10]);
        ReferralReward::create($reward + ['kind' => 'commission', 'reward_key' => 'commission:2', 'amount' => 40, 'base_amount' => 400, 'rate' => 10, 'status' => 'reversed']);

        $this->actingAs($admin)->withoutMiddleware()->get(route('admin.referrals.index'))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Referral/Index')
                ->has('rows', 2)
                ->where('totals.count', 2)->where('totals.trials', 1)->where('totals.subscribed', 1)
                ->where('totals.paid_to_referrers', 70)->where('totals.reversed', 40)->where('totals.discounts_given', 50));

        $this->actingAs($admin)->withoutMiddleware()->get(route('admin.referrals.index', ['status' => 'subscribed']))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.referred.name', 'Paid One')->where('rows.0.trial_bonus', 10)->where('rows.0.commission', 60));

        /* A reversed commission is not counted as earned. */
        $this->actingAs($admin)->withoutMiddleware()->get(route('admin.referrals.index', ['status' => 'waiting']))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.commission', 0)->where('rows.0.trial_bonus', 0)->where('rows.0.discount_used', false));

        $this->actingAs($admin)->withoutMiddleware()->get(route('admin.referrals.index', ['source' => 'card', 'search' => '111111']))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.referred.name', 'Waiting One'));
    }
}
