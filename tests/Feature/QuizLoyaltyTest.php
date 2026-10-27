<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\QuizBadgeDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Loyalty points from the app's quiz: +10 right, -10 wrong, may go below zero, idempotent upload. */
class QuizLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    private function answer(string $id, bool $correct, array $extra = []): array
    {
        return array_merge(['id' => $id, 'play_id' => 'play-1', 'quiz' => 'mcq:football', 'question_id' => 1, 'correct' => $correct, 'answered_at' => now()->toIso8601String()], $extra);
    }

    /** @test */
    public function it_needs_a_token(): void
    {
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => [$this->answer('a', true)]])->assertUnauthorized();
        $this->getJson('/api/v1/loyalty')->assertUnauthorized();
    }

    /** @test */
    public function the_backend_decides_the_points(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => [
            // A client-sent `points` is ignored.
            $this->answer('a', true, ['points' => 9999]),
            $this->answer('b', true),
            $this->answer('c', false, ['timed_out' => true]),
        ]])->assertOk()->assertJsonPath('data.accepted', 3)->assertJsonPath('data.points', 10);

        $this->assertSame(10, LoyaltyTransaction::balanceOf($user->id));
    }

    /** @test */
    public function the_balance_can_go_below_zero(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => [$this->answer('a', false), $this->answer('b', false)]])
            ->assertOk()->assertJsonPath('data.points', -20);
    }

    /** @test */
    public function sending_the_same_answers_twice_books_them_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $body = ['answers' => [$this->answer('a', true), $this->answer('b', false)]];

        $this->postJson('/api/v1/loyalty/quiz-answers', $body)->assertOk()->assertJsonPath('data.accepted', 2);
        $this->postJson('/api/v1/loyalty/quiz-answers', $body)->assertOk()->assertJsonPath('data.accepted', 0)->assertJsonPath('data.duplicates', 2)->assertJsonPath('data.points', 0);

        $this->assertSame(2, LoyaltyTransaction::query()->where('user_id', $user->id)->count());
    }

    /** @test */
    public function members_do_not_share_points_or_ids(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();

        Sanctum::actingAs($one);
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => [$this->answer('same', true)]])->assertOk();

        Sanctum::actingAs($two);
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => [$this->answer('same', false)]])->assertOk()->assertJsonPath('data.points', -10);
        $this->getJson('/api/v1/loyalty')->assertOk()->assertJsonPath('data.points', -10)->assertJsonPath('data.wrong_answers', 1);
    }

    /** @test */
    public function it_refuses_a_malformed_or_oversized_batch(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => []])->assertUnprocessable();
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => [['id' => 'x']]])->assertUnprocessable();

        $many = array_map(fn ($i) => $this->answer("id-$i", true), range(1, 101));
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $many])->assertUnprocessable();
    }

    private function batch(string $prefix, array $pattern, int $stage = 1): array
    {
        return array_map(fn (bool $correct, int $i) => $this->answer("$prefix-$i", $correct, ['question_id' => $i + 1, 'stage' => $stage, 'answered_at' => now()->subSeconds(1000 - $i)->toIso8601String()]), $pattern, array_keys($pattern));
    }

    /** @test */
    public function ten_right_answers_earn_the_first_solved_badge_once(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $first = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('a', array_fill(0, 10, true))])->assertOk();
        $this->assertContains('solved_10', array_column($first->json('data.new_badges'), 'key'));
        $this->assertContains('streak_10', array_column($first->json('data.new_badges'), 'key'));

        $second = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('b', array_fill(0, 3, true))])->assertOk();
        $this->assertSame([], $second->json('data.new_badges'));
    }

    /** @test */
    public function a_wrong_answer_breaks_the_streak(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $pattern = array_merge(array_fill(0, 6, true), [false], array_fill(0, 6, true));
        $response = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('s', $pattern)])->assertOk();

        $this->assertSame(6, $response->json('data.stats.mcq:football.best_streak'));
        $this->assertNotContains('streak_10', array_column($response->json('data.new_badges'), 'key'));
    }

    /** @test */
    public function reaching_a_new_stage_earns_the_stage_badge(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('g', [true, true], stage: 2)])->assertOk();

        $this->assertContains('stage_2', array_column($response->json('data.new_badges'), 'key'));
    }

    /** @test */
    public function the_catalog_lists_every_badge_with_progress_and_belongs_to_the_member(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('c', array_fill(0, 12, true))])->assertOk();

        $catalog = collect($this->getJson('/api/v1/loyalty')->assertOk()->json('data.badges'));
        $football = $catalog->where('quiz', 'mcq:football');
        // 3 kinds x 5 levels = 15 badges in each of the four sections.
        $this->assertSame(60, $catalog->count());
        $this->assertSame(15, $football->count());
        $this->assertTrue($football->firstWhere('key', 'solved_10')['earned']);
        $this->assertFalse($football->firstWhere('key', 'solved_50')['earned']);
        $this->assertSame(12, $football->firstWhere('key', 'solved_50')['progress']);
        // Right answers in football earn nothing in the other section.
        $this->assertFalse($catalog->where('quiz', 'mcq:fawazeer')->firstWhere('key', 'solved_10')['earned']);

        Sanctum::actingAs(User::factory()->create());
        $this->assertFalse(collect($this->getJson('/api/v1/loyalty')->json('data.badges'))->where('quiz', 'mcq:football')->firstWhere('key', 'solved_10')['earned']);
    }

    /** @test */
    public function earning_a_badge_pays_its_reward_points_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 10 right answers: 10 x 10 points, plus solved_10 (10), streak_5 (10) and streak_10 (20) from the seeded rewards.
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('r', array_fill(0, 10, true))])->assertOk()->assertJsonPath('data.points', 140);

        // The same batch again books nothing, rewards included.
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('r', array_fill(0, 10, true))])->assertOk()->assertJsonPath('data.points', 140);
        $this->assertSame(3, LoyaltyTransaction::query()->where('user_id', $user->id)->where('source', 'badge')->count());
    }

    /** @test */
    public function the_admin_controls_what_a_badge_needs_and_pays(): void
    {
        Sanctum::actingAs(User::factory()->create());

        QuizBadgeDefinition::query()->where('kind', 'solved')->where('threshold', 10)->update(['threshold' => 3, 'reward_points' => 77, 'title' => 'ثلاثة صح']);
        QuizBadgeDefinition::query()->where('kind', 'streak')->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('d', array_fill(0, 3, true))])->assertOk();

        $new = collect($response->json('data.new_badges'));
        $this->assertSame(['solved_3'], $new->pluck('key')->all());
        $this->assertSame('ثلاثة صح', $new->first()['title']);
        $this->assertSame(77, $new->first()['reward_points']);
        $this->assertSame(30 + 77, $response->json('data.points'));
        // A switched-off badge is neither earned nor listed.
        $this->assertNull(collect($response->json('data.badges'))->firstWhere('kind', 'streak'));
    }

    /** @test */
    public function the_history_lists_plays_badges_and_admin_grants_newest_first(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->batch('h', array_fill(0, 10, true))])->assertOk();
        LoyaltyTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'admin:1', 'points' => 500, 'note' => 'مكافأة', 'created_at' => now()->addMinute()]);

        $entries = collect($this->getJson('/api/v1/loyalty/history')->assertOk()->json('data.entries'));

        $this->assertSame('admin', $entries->first()['type']);
        $this->assertSame(500, $entries->first()['points']);
        $this->assertSame('مكافأة', $entries->first()['note']);
        $this->assertSame(['admin', 'badge', 'play'], $entries->pluck('type')->unique()->sort()->values()->all());
        $play = $entries->firstWhere('type', 'play');
        $this->assertSame([10, 0, 100], [$play['right'], $play['wrong'], $play['points']]);
        $this->assertSame(10 + 10 + 20, $entries->where('type', 'badge')->sum('points'));
    }

    /** One short play: `$n` answers sharing a play id, on the given section, at the given time. */
    private function play(string $playId, int $n = 2, string $quiz = 'mcq:football', ?string $at = null): array
    {
        return array_map(fn (int $i) => $this->answer("$playId-$i", true, ['play_id' => $playId, 'quiz' => $quiz, 'question_id' => $i + 1, 'answered_at' => $at ?? now()->toIso8601String()]), range(1, $n));
    }

    /** @test */
    public function a_section_allows_five_plays_a_day_and_the_sixth_earns_nothing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $answers = [];
        foreach (range(1, 6) as $n) {
            $answers = array_merge($answers, $this->play("p$n"));
        }

        $response = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $answers])->assertOk();

        $this->assertSame(10, $response->json('data.accepted'));
        $this->assertSame(2, $response->json('data.over_daily_limit'));
        $this->assertSame(5, LoyaltyTransaction::query()->where('user_id', $user->id)->where('source', 'quiz')->distinct()->count('play_id'));
        $this->assertDatabaseMissing('loyalty_transactions', ['user_id' => $user->id, 'play_id' => 'p6']);

        // Sending the sixth play again, or in a later request the same day, is still refused; the five stay.
        $again = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $this->play('p6')])->assertOk();
        $this->assertSame(0, $again->json('data.accepted'));
        $this->assertSame(2, $again->json('data.over_daily_limit'));
        // More answers of a play that already counts are fine.
        $more = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => [$this->answer('p1-extra', true, ['play_id' => 'p1', 'quiz' => 'mcq:football'])]])->assertOk();
        $this->assertSame(1, $more->json('data.accepted'));
    }

    /** @test */
    public function each_section_has_its_own_five_so_two_sections_allow_ten(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $answers = [];
        foreach (range(1, 5) as $n) {
            $answers = array_merge($answers, $this->play("f$n", 1, 'mcq:football'), $this->play("w$n", 1, 'mcq:fawazeer'));
        }

        $response = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $answers])->assertOk();

        $this->assertSame(10, $response->json('data.accepted'));
        $this->assertSame(0, $response->json('data.over_daily_limit'));
    }

    /** @test */
    public function the_allowance_is_back_the_next_day(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $yesterday = now()->subDay()->toIso8601String();

        $answers = [];
        foreach (range(1, 6) as $n) {
            $answers = array_merge($answers, $this->play("y$n", 1, 'mcq:football', $yesterday));
        }
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $answers])->assertOk()->assertJsonPath('data.accepted', 5)->assertJsonPath('data.over_daily_limit', 1);

        // Today starts from zero again.
        $today = [];
        foreach (range(1, 5) as $n) {
            $today = array_merge($today, $this->play("t$n", 1));
        }
        $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $today])->assertOk()->assertJsonPath('data.accepted', 5)->assertJsonPath('data.over_daily_limit', 0);
    }

    /** @test */
    public function the_fawazeer_section_has_its_own_badges(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $answers = array_map(fn (int $i) => $this->answer("w-$i", true, ['play_id' => 'w1', 'quiz' => 'mcq:fawazeer', 'question_id' => $i]), range(1, 10));
        $response = $this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $answers])->assertOk();

        $new = collect($response->json('data.new_badges'));
        $this->assertSame(['mcq:fawazeer'], $new->pluck('quiz')->unique()->values()->all());
        $this->assertContains('solved_10', $new->pluck('key')->all());
    }

    /** @test */
    public function every_section_starts_with_its_default_badges_and_earns_them_on_its_own(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $sections = ['mcq:football', 'mcq:fawazeer', 'mcq:general', 'mcq:history'];

        foreach ($sections as $quiz) {
            $this->assertSame(15, QuizBadgeDefinition::query()->where('quiz_key', $quiz)->count(), $quiz);
        }

        foreach (['mcq:general', 'mcq:history'] as $quiz) {
            $answers = array_map(fn (int $i) => $this->answer("$quiz-$i", true, ['play_id' => "play-$quiz", 'quiz' => $quiz, 'question_id' => $i]), range(1, 10));
            $new = collect($this->postJson('/api/v1/loyalty/quiz-answers', ['answers' => $answers])->assertOk()->json('data.new_badges'));

            $this->assertSame([$quiz], $new->pluck('quiz')->unique()->values()->all());
            $this->assertContains('solved_10', $new->pluck('key')->all());
        }
    }

    /** @test */
    public function anyone_can_list_all_the_badges_without_signing_in(): void
    {
        QuizBadgeDefinition::query()->where('kind', 'stage')->where('threshold', 5)->update(['is_active' => false, 'title' => 'مخفية']);
        QuizBadgeDefinition::query()->where('kind', 'solved')->where('threshold', 100)->update(['title' => 'مئة صح', 'icon' => '💯', 'reward_points' => 123]);

        $badges = collect($this->getJson('/api/v1/mobile/loyalty/badges')->assertOk()->json('data.badges'));

        $this->assertSame(56, $badges->count());
        $this->assertNull($badges->firstWhere('key', 'stage_5'));
        $hundred = $badges->firstWhere('key', 'solved_100');
        $this->assertSame(['مئة صح', '💯', 123, 'solved', 100], [$hundred['title'], $hundred['icon'], $hundred['reward_points'], $hundred['kind'], $hundred['threshold']]);
        // It is the catalog, not a member's record: no progress or earned flags.
        $this->assertArrayNotHasKey('earned', $hundred);
    }
}
