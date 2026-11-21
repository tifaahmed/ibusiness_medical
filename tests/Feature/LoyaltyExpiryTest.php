<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Support\LoyaltyExpiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Points expire months after they were earned; spending takes the oldest points first so spent points never expire. */
class LoyaltyExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function row(User $u, string $id, int $points, string $when): void
    {
        LoyaltyTransaction::query()->create(['user_id' => $u->id, 'source' => $points > 0 ? 'admin' : 'order', 'client_id' => $id, 'points' => $points, 'answered_at' => $when]);
    }

    public function test_only_old_unspent_points_expire_and_a_rerun_books_nothing(): void
    {
        $u = User::factory()->create();
        $this->row($u, 'a', 100, now()->subMonths(8)->toDateTimeString());
        $this->row($u, 'b', 50, now()->subMonths(2)->toDateTimeString());
        $this->row($u, 'spend', -30, now()->subMonth()->toDateTimeString());

        $this->assertSame(70, LoyaltyExpiry::expireFor($u->id));
        $this->assertSame(50, LoyaltyTransaction::balanceOf($u->id));
        $this->assertSame(0, LoyaltyExpiry::expireFor($u->id));
        $this->assertSame(50, LoyaltyTransaction::balanceOf($u->id));
    }

    public function test_spending_takes_the_oldest_points_so_they_are_not_expired_twice(): void
    {
        $u = User::factory()->create();
        $this->row($u, 'old', 100, now()->subMonths(8)->toDateTimeString());
        $this->row($u, 'new', 100, now()->subMonth()->toDateTimeString());
        $this->row($u, 'spend', -100, now()->toDateTimeString());

        $this->assertSame(0, LoyaltyExpiry::expireFor($u->id));
        $this->assertSame(100, LoyaltyTransaction::balanceOf($u->id));
        $this->assertSame([['id' => LoyaltyTransaction::where('client_id', 'new')->value('id'), 'remaining' => 100]], array_map(fn ($l) => ['id' => $l['id'], 'remaining' => $l['remaining']], LoyaltyExpiry::lots($u->id)));
    }

    public function test_command_does_nothing_while_the_switch_is_off(): void
    {
        $u = User::factory()->create();
        $this->row($u, 'a', 100, now()->subMonths(8)->toDateTimeString());

        $this->artisan('loyalty:expire-points')->assertSuccessful();
        $this->assertSame(100, LoyaltyTransaction::balanceOf($u->id));

        $this->artisan('loyalty:expire-points --force')->assertSuccessful();
        $this->assertSame(0, LoyaltyTransaction::balanceOf($u->id));
    }

    public function test_a_member_is_warned_about_points_expiring_within_a_month(): void
    {
        \App\Support\SiteSettings::put(LoyaltyExpiry::ENABLED, 1, \App\Models\Setting::TYPE_BOOLEAN);
        \App\Support\SiteSettings::forget();
        $u = User::factory()->create();
        $this->row($u, 'soon', 40, now()->subMonths(6)->addDays(10)->toDateTimeString());
        $this->row($u, 'later', 60, now()->subMonths(2)->toDateTimeString());

        $e = LoyaltyExpiry::expiringSoon($u->id);
        $this->assertSame(40, $e['points']);
        $this->assertGreaterThanOrEqual(9, $e['days']);
        $this->assertLessThanOrEqual(10, $e['days']);
    }

    public function test_command_works_in_groups_and_only_on_members_with_old_points(): void
    {
        $old = User::factory()->count(3)->create();
        $fresh = User::factory()->create();
        foreach ($old as $i => $u) {
            $this->row($u, "o{$i}", 10, now()->subMonths(9)->toDateTimeString());
        }
        $this->row($fresh, 'f', 10, now()->toDateTimeString());

        $this->artisan('loyalty:expire-points --force --chunk=2 --pause=0')->expectsOutputToContain('in 2 group(s)')->assertSuccessful();
        $this->assertSame(10, LoyaltyTransaction::balanceOf($fresh->id));
        $this->assertSame(0, LoyaltyTransaction::balanceOf($old[2]->id));
    }
}
