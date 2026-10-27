<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyTransaction;
use App\Models\QuizBadge;
use App\Models\QuizBadgeDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The quiz's badges, worked out from the member's booked answers (never from what a client claims): total right answers,
 * the longest run of right answers in a row (in the order they were answered, across plays) and the stage reached.
 * What earns a badge, and what it pays, is admin data (`quiz_badge_definitions`, /admin/loyalty). A badge, once
 * earned, stays; earning it books its reward points in the loyalty ledger once.
 */
class QuizBadges
{
    /** @return array{correct: int, best_streak: int, max_stage: int} */
    public function stats(int $userId, string $quizKey): array
    {
        $rows = LoyaltyTransaction::query()->where('user_id', $userId)->where('source', LoyaltyTransaction::SOURCE_QUIZ)->where('quiz_key', $quizKey);

        $correct = 0;
        $run = 0;
        $best = 0;

        foreach ((clone $rows)->orderBy('answered_at')->orderBy('id')->select(['id', 'correct'])->cursor() as $row) {
            if ($row->correct) {
                $correct++;
                $best = max($best, ++$run);
            } else {
                $run = 0;
            }
        }

        return ['correct' => $correct, 'best_streak' => $best, 'max_stage' => max(1, (int) (clone $rows)->max('stage'))];
    }

    /** The value a badge kind is measured by. */
    private function value(array $stats, string $kind): int
    {
        return match ($kind) {
            'solved' => $stats['correct'],
            'streak' => $stats['best_streak'],
            'stage' => $stats['max_stage'],
            default => 0,
        };
    }

    /** The badge as the apps get it. */
    private function present(QuizBadgeDefinition $d): array
    {
        return [
            'quiz' => $d->quiz_key,
            'key' => $d->key(),
            'kind' => $d->kind,
            'threshold' => $d->threshold,
            'title' => $d->title,
            'icon' => $d->icon,
            'reward_points' => $d->reward_points,
        ];
    }

    /**
     * Gives the member every active badge of this section they now qualify for, and books each one's reward.
     *
     * @return list<array<string, mixed>> the badges earned just now
     */
    public function award(int $userId, string $quizKey): array
    {
        $definitions = QuizBadgeDefinition::query()->where('quiz_key', $quizKey)->where('is_active', true)->get();

        if ($definitions->isEmpty()) {
            return [];
        }

        $stats = $this->stats($userId, $quizKey);
        $have = QuizBadge::query()->where('user_id', $userId)->where('quiz_key', $quizKey)->pluck('definition_id')->filter()->all();
        $new = [];

        foreach ($definitions as $definition) {
            if ($this->value($stats, $definition->kind) < $definition->threshold || in_array($definition->id, $have, true)) {
                continue;
            }

            try {
                DB::transaction(function () use ($userId, $definition): void {
                    QuizBadge::query()->create([
                        'user_id' => $userId, 'definition_id' => $definition->id, 'quiz_key' => $definition->quiz_key,
                        'badge_key' => $definition->key(), 'reward_points' => $definition->reward_points, 'earned_at' => now(),
                    ]);

                    if ($definition->reward_points > 0) {
                        LoyaltyTransaction::query()->create([
                            'user_id' => $userId, 'source' => LoyaltyTransaction::SOURCE_BADGE, 'client_id' => "badge:{$definition->id}",
                            'quiz_key' => $definition->quiz_key, 'points' => $definition->reward_points, 'badge_definition_id' => $definition->id,
                            'note' => $definition->title ?: $definition->key(), 'answered_at' => now(),
                        ]);
                    }
                });
                $new[] = $this->present($definition);
            } catch (\Throwable $e) {
                // A parallel upload may have booked it first (unique indexes): the member has the badge either way.
                Log::warning('Quiz badge not booked', ['user_id' => $userId, 'quiz' => $quizKey, 'badge' => $definition->key(), 'error' => $e->getMessage()]);
            }
        }

        return $new;
    }

    /**
     * Every active badge of every section, the same for everyone (no member needed): what the app shows as "all the badges".
     *
     * @return list<array<string, mixed>>
     */
    public function publicCatalog(): array
    {
        return QuizBadgeDefinition::query()->where('is_active', true)->orderBy('quiz_key')->orderBy('kind')->orderBy('threshold')->get()
            ->map(fn (QuizBadgeDefinition $d) => $this->present($d))->all();
    }

    /**
     * Every active badge of every section, earned or not, with how far along the member is.
     *
     * @return array{badges: list<array<string, mixed>>, stats: array<string, array<string, int>>}
     */
    public function catalog(int $userId): array
    {
        $definitions = QuizBadgeDefinition::query()->where('is_active', true)->orderBy('quiz_key')->orderBy('kind')->orderBy('threshold')->get();
        $earned = QuizBadge::query()->where('user_id', $userId)->get()->keyBy('definition_id');

        $badges = [];
        $stats = [];

        foreach ($definitions as $definition) {
            $stats[$definition->quiz_key] ??= $this->stats($userId, $definition->quiz_key);
            $mine = $earned->get($definition->id);

            $badges[] = $this->present($definition) + [
                'progress' => min($definition->threshold, $this->value($stats[$definition->quiz_key], $definition->kind)),
                'earned' => $mine !== null,
                'earned_at' => $mine?->earned_at?->toIso8601String(),
            ];
        }

        return ['badges' => $badges, 'stats' => $stats];
    }
}
