<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\LoyaltyPoints;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The admin's member page shows the member's points, wallet, plays, conversions and relatives (read only). */
class AdminMemberLoyaltyTabTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        foreach (['manage memberships', 'view memberships', 'view users', 'manage users'] as $p) {
            Permission::findOrCreate($p, 'web');
            $admin->givePermissionTo($p);
        }

        return $admin;
    }

    /** @test */
    public function the_member_page_carries_points_wallet_plays_conversions_transfers_and_family(): void
    {
        SiteSettings::put(LoyaltyPoints::POINTS_PER_EGP, '10', Setting::TYPE_NUMBER);
        SiteSettings::forget();

        $member = User::factory()->create(['slug' => 'mona-hassan']);
        $primary = Membership::factory()->create(['user_id' => $member->id]);
        $relativeUser = User::factory()->create(['name' => 'Little Ali', 'phone' => '01055556666']);
        Membership::factory()->create(['user_id' => $relativeUser->id, 'member_type' => 'relative', 'parent_membership_id' => $primary->id, 'relationship' => 'son', 'plan' => 'relative']);

        // two plays of two answers each, and one conversion of 300 points into 30 pounds
        foreach (['p1' => [true, 100], 'p2' => [false, -100]] as $play => [$right, $points]) {
            foreach ([1, 2] as $n) {
                LoyaltyTransaction::query()->create(['user_id' => $member->id, 'source' => 'quiz', 'client_id' => "$play-$n", 'play_id' => $play, 'quiz_key' => 'mcq:general', 'correct' => $right, 'points' => $points, 'answered_at' => now()]);
            }
        }
        LoyaltyTransaction::query()->create(['user_id' => $member->id, 'source' => 'wallet', 'client_id' => 'conv-1', 'points' => -300, 'answered_at' => now()]);
        WalletTransaction::query()->create(['user_id' => $member->id, 'source' => 'points', 'client_id' => 'conv-1', 'amount' => 30, 'points' => 300]);
        LoyaltyTransaction::query()->create(['user_id' => $relativeUser->id, 'source' => 'admin', 'client_id' => 'g1', 'points' => 70, 'answered_at' => now()]);

        $this->actingAs($this->admin())->get(route('admin.user.membership.show', $member->slug))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('loyalty.balance', -300)
                ->where('loyalty.egp_value', 0)
                ->where('loyalty.wallet_balance', 30)
                ->where('loyalty.min_convert_egp', 20)
                ->has('loyalty.plays', 2)
                ->where('loyalty.conversions.0.points', 300)
                ->where('loyalty.conversions.0.egp', 30)
                ->where('loyalty.family', fn ($rows) => count($rows) === 1 && $rows[0]['name'] === 'Little Ali' && $rows[0]['balance'] === 70 && $rows[0]['relationship'] === 'son'));
    }
}
