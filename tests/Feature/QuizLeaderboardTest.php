<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The quiz ranking: by the balance held now, ties share a rank, names are shortened, spending points drops the player. */
class QuizLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $name, int $points): User
    {
        $user = User::factory()->create(['name' => $name]);
        LoyaltyTransaction::query()->create(['user_id' => $user->id, 'source' => LoyaltyTransaction::SOURCE_QUIZ, 'client_id' => "q-{$user->id}", 'quiz_key' => 'mcq:football', 'question_id' => 1, 'correct' => true, 'points' => $points, 'answered_at' => now()]);

        return $user;
    }

    /** @test */
    public function it_ranks_players_by_balance_with_short_names_and_shared_ranks(): void
    {
        $this->player('محمد أحمد علي', 300);
        $this->player('سارة حسن', 200);
        $this->player('ليلى سمير', 200);
        $this->player('عمر خالد', 50);

        $top = $this->getJson('/api/v1/mobile/loyalty/leaderboard')->assertOk()->json('data.top');

        $this->assertSame([1, 2, 2, 4], array_column($top, 'rank'));
        $this->assertSame('محمد ع.', $top[0]['name']);
        $this->assertSame(300, $top[0]['points']);
    }

    /** @test */
    public function spending_points_lowers_the_place_and_me_is_returned_with_a_token(): void
    {
        $first = $this->player('أحمد سعيد', 300);
        $second = $this->player('منى علي', 200);

        LoyaltyTransaction::query()->create(['user_id' => $first->id, 'source' => LoyaltyTransaction::SOURCE_ADMIN, 'client_id' => 'spend-1', 'points' => -250, 'note' => 'spent', 'answered_at' => now()]);

        Sanctum::actingAs($first);
        $response = $this->getJson('/api/v1/mobile/loyalty/leaderboard')->assertOk();

        $this->assertSame('منى ع.', $response->json('data.top.0.name'));
        $this->assertSame(2, $response->json('data.me.rank'));
        $this->assertSame(50, $response->json('data.me.points'));
        $this->assertTrue($response->json('data.top.1.me'));
    }

    /** @test */
    public function someone_who_never_played_is_not_ranked(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->assertNull($this->getJson('/api/v1/mobile/loyalty/leaderboard')->assertOk()->json('data.me'));
    }
}
