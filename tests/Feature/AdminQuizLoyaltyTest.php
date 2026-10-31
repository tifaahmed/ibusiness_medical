<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\QuizBadgeDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * /admin/loyalty: the admin edits the quiz badges (what earns them, what they pay), sees who holds each, and gives
 * or takes back loyalty points by hand. Run on a throwaway database (see AGENTS.md).
 */
class AdminQuizLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('manage memberships', 'web'));

        return $admin;
    }

    private function badgeFields(array $over = []): array
    {
        return $over + ['quiz_key' => 'mcq:football', 'kind' => 'solved', 'threshold' => 7, 'title' => 'سبعة صح', 'icon' => '⭐', 'reward_points' => 15, 'is_active' => true];
    }

    /** @test */
    public function only_staff_with_the_permission_get_in(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.loyalty.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.loyalty.grants.store'), ['user_id' => $member->id, 'points' => 50])->assertForbidden();
        $this->assertSame(0, LoyaltyTransaction::balanceOf($member->id));
    }

    /** @test */
    public function the_page_lists_the_badges_and_finds_members_with_their_balance(): void
    {
        $member = User::factory()->create(['name' => 'Mona Quiz', 'phone' => '01012345678']);
        LoyaltyTransaction::query()->create(['user_id' => $member->id, 'source' => 'admin', 'client_id' => 'admin:seed', 'points' => 40]);

        $this->actingAs($this->admin())->get(route('admin.loyalty.index', ['search' => '0101234']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Loyalty/Index')
                ->has('badges', 75)->where('pointsPerEgp', 10)->has('speedTiers', 4)
                ->has('members', 1)
                ->where('members.0.balance', 40)
                ->where('members.0.name', 'Mona Quiz'));
    }

    /** @test */
    public function the_admin_adds_edits_and_switches_off_a_badge(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.loyalty.badges.store'), $this->badgeFields())->assertRedirect();
        $badge = QuizBadgeDefinition::query()->where('threshold', 7)->firstOrFail();
        $this->assertSame(15, $badge->reward_points);

        $this->actingAs($admin)->put(route('admin.loyalty.badges.update', $badge), $this->badgeFields(['reward_points' => 99, 'is_active' => false]))->assertRedirect();
        $this->assertSame(99, $badge->fresh()->reward_points);
        $this->assertFalse($badge->fresh()->is_active);

        // A switched-off badge is not earned.
        $member = User::factory()->create();
        Sanctum::actingAs($member);
        $response = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => array_map(fn ($i) => ['id' => "x-$i", 'quiz' => 'mcq:football', 'correct' => true, 'answered_at' => now()->toIso8601String()], range(1, 7))])->assertOk();
        $this->assertNotContains('solved_7', array_column($response->json('data.new_badges'), 'key'));
    }

    /** @test */
    public function a_badge_is_refused_when_it_repeats_another_or_names_an_unknown_section(): void
    {
        $admin = $this->admin();

        // solved 10 in the football section is seeded.
        $this->actingAs($admin)->post(route('admin.loyalty.badges.store'), $this->badgeFields(['threshold' => 10]))->assertSessionHasErrors('threshold');
        $this->actingAs($admin)->post(route('admin.loyalty.badges.store'), $this->badgeFields(['quiz_key' => 'made-up']))->assertSessionHasErrors('quiz_key');
        $this->actingAs($admin)->post(route('admin.loyalty.badges.store'), $this->badgeFields(['kind' => 'weird']))->assertSessionHasErrors('kind');
        $this->actingAs($admin)->post(route('admin.loyalty.badges.store'), $this->badgeFields(['reward_points' => -5]))->assertSessionHasErrors('reward_points');
        $this->assertSame(0, QuizBadgeDefinition::query()->where('threshold', 7)->count());
    }

    /** @test */
    public function the_admin_sees_who_holds_a_badge(): void
    {
        $holder = User::factory()->create(['name' => 'Holder One']);
        Sanctum::actingAs($holder);
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => array_map(fn ($i) => ['id' => "h-$i", 'quiz' => 'mcq:football', 'correct' => true, 'answered_at' => now()->toIso8601String()], range(1, 10))])->assertOk();
        $badge = QuizBadgeDefinition::query()->where('quiz_key', 'mcq:football')->where('kind', 'solved')->where('threshold', 10)->firstOrFail();

        $rows = $this->actingAs($this->admin())->getJson(route('admin.loyalty.badges.holders', $badge))->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('Holder One', $rows[0]['name']);
        $this->assertSame(10, $rows[0]['reward_points']);
    }

    /** @test */
    public function extra_points_reach_the_member_with_the_note_and_show_in_their_history(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.loyalty.grants.store'), ['user_id' => $member->id, 'points' => 250, 'note' => 'هدية العيد'])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.loyalty.grants.store'), ['user_id' => $member->id, 'points' => -50, 'note' => 'تصحيح'])->assertRedirect();

        $this->assertSame(200, LoyaltyTransaction::balanceOf($member->id));
        $this->assertDatabaseHas('loyalty_transactions', ['user_id' => $member->id, 'source' => 'admin', 'points' => 250, 'note' => 'هدية العيد', 'created_by' => $admin->id]);

        Sanctum::actingAs($member);
        $entries = collect($this->getJson('/api/v1/loyalty/history')->assertOk()->json('data.entries'));
        $this->assertSame(['admin', 'admin'], $entries->pluck('type')->all());
        $this->assertEqualsCanonicalizing([250, -50], $entries->pluck('points')->all());
        $this->assertContains('هدية العيد', $entries->pluck('note')->all());
        $this->getJson('/api/v1/loyalty')->assertJsonPath('data.points', 200)->assertJsonPath('data.right_answers', 0);
    }

    /** @test */
    public function a_grant_needs_a_real_member_and_a_non_zero_number(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.loyalty.grants.store'), ['user_id' => $member->id, 'points' => 0])->assertSessionHasErrors('points');
        $this->actingAs($admin)->post(route('admin.loyalty.grants.store'), ['user_id' => $member->id, 'points' => 'abc'])->assertSessionHasErrors('points');
        $this->actingAs($admin)->post(route('admin.loyalty.grants.store'), ['user_id' => 999999, 'points' => 10])->assertSessionHasErrors('user_id');
        $this->assertSame(0, LoyaltyTransaction::query()->where('source', 'admin')->count());
    }
}
