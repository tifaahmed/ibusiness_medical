<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** /admin/loyalty/log lists only what an admin wrote: who, which member and card, how much, why. */
class LoyaltyAdjustmentLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_admin_lines_of_both_ledgers_with_filters(): void
    {
        $admin = User::factory()->create(['name' => 'Boss']);
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));
        $member = User::factory()->create(['name' => 'Fryal']);
        Membership::factory()->create(['user_id' => $member->id, 'membership_number' => '445566']);

        LoyaltyTransaction::create(['user_id' => $member->id, 'source' => 'admin', 'client_id' => 'a1', 'points' => 50, 'note' => 'gift', 'created_by' => $admin->id, 'answered_at' => now()]);
        LoyaltyTransaction::create(['user_id' => $member->id, 'source' => 'admin', 'client_id' => 'a2', 'points' => -20, 'note' => 'mistake', 'created_by' => $admin->id, 'answered_at' => now()]);
        LoyaltyTransaction::create(['user_id' => $member->id, 'source' => 'quiz', 'client_id' => 'q1', 'points' => 100, 'answered_at' => now()]); // the game: not an admin line
        WalletTransaction::create(['user_id' => $member->id, 'source' => 'admin', 'client_id' => 'w1', 'amount' => 12.5, 'note' => 'refund by hand', 'created_by' => $admin->id]);

        $this->actingAs($admin)->withoutMiddleware()->get(route('admin.points-wallet-log'))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Loyalty/Log')
                ->has('rows', 3)
                ->where('rows.0.admin', 'Boss')->where('rows.0.member.name', 'Fryal')->where('rows.0.member.membership_number', '445566')
                ->where('totals.points.given', 50)->where('totals.points.taken', -20)->where('totals.wallet.given', 12.5));

        $this->actingAs($admin)->withoutMiddleware()->get(route('admin.points-wallet-log', ['ledger' => 'wallet']))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.ledger', 'wallet'));
        $this->actingAs($admin)->withoutMiddleware()->get(route('admin.points-wallet-log', ['direction' => 'taken', 'search' => '445566']))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.note', 'mistake'));
        $this->actingAs($admin)->withoutMiddleware()->get(route('admin.points-wallet-log', ['search' => 'nobody-here']))
            ->assertInertia(fn (Assert $p) => $p->has('rows', 0));
    }
}
