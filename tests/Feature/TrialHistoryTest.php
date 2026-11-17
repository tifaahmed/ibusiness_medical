<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipTrial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Needs a throwaway MySQL database (name ends in `_test`) — never the live one. See AGENTS.md "NEVER LOSE DATA".
 */
class TrialHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function trialCard(string $end, string $expiry): Membership
    {
        $user = User::factory()->create();

        return Membership::query()->forceCreate([
            'user_id' => $user->id, 'membership_number' => (string) random_int(100000, 999999), 'slug' => 'tt'.random_int(1000, 9999),
            'registration_date' => now()->subDays(5), 'expiration_date' => $expiry, 'trial_started_at' => now()->subDays(5),
            'trial_ends_at' => $end, 'plan' => Membership::PLAN_TRIAL, 'is_active' => true, 'is_visible' => true, 'is_paid' => false,
        ]);
    }

    public function test_a_new_trial_writes_a_started_row_and_a_later_date_writes_extended(): void
    {
        $m = $this->trialCard(now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString());
        $this->assertSame(['started'], $m->trials()->pluck('event')->all());

        $m->update(['trial_ends_at' => now()->addDays(20)->toDateString()]);

        $this->assertSame(['started', 'extended'], $m->trials()->pluck('event')->all());
        $this->assertSame(1, $m->trials()->where('event', 'extended')->count());
    }

    public function test_ending_a_trial_is_recorded_and_rows_are_never_removed(): void
    {
        $m = $this->trialCard(now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString());
        $m->update(['plan' => Membership::PLAN_FULL]);

        $this->assertSame(['started', 'ended'], $m->trials()->pluck('event')->all());
        $this->assertSame(2, MembershipTrial::query()->where('membership_id', $m->id)->count());
    }

    public function test_trial_is_locked_once_its_date_passed_and_a_running_membership_is_then_a_normal_member(): void
    {
        $m = $this->trialCard(now()->subDay()->toDateString(), now()->addDays(30)->toDateString());

        $this->assertTrue($m->trialLocked());
        $this->assertFalse($m->isTrial());
    }

    public function test_when_both_dates_passed_it_stays_a_finished_trial(): void
    {
        $m = $this->trialCard(now()->subDays(3)->toDateString(), now()->subDay()->toDateString());

        $this->assertTrue($m->trialLocked());
        $this->assertTrue($m->isTrial());
        $this->assertSame(Membership::STATE_TRIAL_EXPIRED, $m->state());
    }
}
