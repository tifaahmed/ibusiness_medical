<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Models\QuizBadge;
use App\Models\User;
use App\Services\Loyalty\QuizBadges;
use App\Models\WalletTransaction;
use App\Support\LoyaltyPoints;
use App\Support\LoyaltyWallet;
use App\Support\WalletPayments;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * A signed-in member's loyalty points, earned by the quiz game in the app.
 *
 * The phone plays offline and keeps every answer; whenever it is open and online it
 * posts what it has not sent yet. Each answer carries an id the PHONE made, unique per
 * member, so sending the same batch twice (a lost reply, a retry) books it once. The
 * points are decided HERE from `correct` (config/loyalty.php) — a client never sends them.
 * Token-gated like the rest of the member endpoints: `$request->user()` is the member.
 */
class LoyaltyController extends Controller
{
    public function __construct(private readonly QuizBadges $badges) {}

    /** Every badge on offer, for anyone (no sign-in): the "all badges" screen of the app, also for a player who has no account yet. */
    public function badgeCatalog(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['badges' => $this->badges->publicCatalog(), 'speed_tiers' => $this->speedTiers(), 'points_per_egp' => LoyaltyPoints::pointsPerEgp()]]);
    }

    public function show(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        return response()->json(['success' => true, 'data' => $this->summary($member)]);
    }

    /**
     * The member's loyalty log, newest first, in one sequence: each quiz play (its right / wrong answers and the points
     * it won or lost), each badge earned with the reward it paid, and each grant by an admin with its note.
     */
    public function history(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $entries = [];

        $quizRows = LoyaltyTransaction::query()->where('user_id', $member->id)->where('source', LoyaltyTransaction::SOURCE_QUIZ)
            ->orderByDesc('answered_at')->limit(3000)->get(['client_id', 'play_id', 'quiz_key', 'correct', 'points', 'answered_at']);

        foreach ($quizRows->groupBy(fn ($row) => $row->play_id ?: $row->client_id) as $playId => $rows) {
            $entries[] = [
                'type' => 'play',
                'at' => $rows->min('answered_at')?->toIso8601String(),
                'play_id' => (string) $playId,
                'quiz' => $rows->first()->quiz_key,
                'right' => $rows->where('correct', true)->count(),
                'wrong' => $rows->where('correct', false)->count(),
                'points' => (int) $rows->sum('points'),
            ];
        }

        foreach (QuizBadge::query()->with('definition')->where('user_id', $member->id)->get() as $badge) {
            $entries[] = [
                'type' => 'badge',
                'at' => $badge->earned_at?->toIso8601String(),
                'quiz' => $badge->quiz_key,
                'key' => $badge->badge_key,
                'kind' => $badge->definition?->kind,
                'threshold' => $badge->definition?->threshold,
                'title' => $badge->definition?->title,
                'icon' => $badge->definition?->icon,
                'points' => $badge->reward_points,
            ];
        }

        foreach (LoyaltyTransaction::query()->where('user_id', $member->id)->where('source', LoyaltyTransaction::SOURCE_ADMIN)->get() as $grant) {
            $entries[] = ['type' => 'admin', 'at' => $grant->created_at?->toIso8601String(), 'points' => $grant->points, 'note' => $grant->note];
        }

        foreach (LoyaltyTransaction::query()->where('user_id', $member->id)->where('source', LoyaltyTransaction::SOURCE_WALLET)->get() as $spent) {
            $entries[] = ['type' => 'wallet', 'at' => $spent->created_at?->toIso8601String(), 'points' => $spent->points, 'note' => $spent->note];
        }

        foreach (LoyaltyTransaction::query()->where('user_id', $member->id)->where('source', LoyaltyTransaction::SOURCE_TRANSFER)->get() as $moved) {
            $entries[] = ['type' => 'transfer', 'at' => $moved->created_at?->toIso8601String(), 'points' => $moved->points, 'note' => null];
        }

        // Newest first. Timestamps are whole seconds, and things that happen together (a play, the badge it earned, the points
        // an admin then gave) share one: a tie goes to what logically came later — admin grant, then badge, then play.
        $rank = ['transfer' => 4, 'wallet' => 4, 'admin' => 3, 'badge' => 2, 'play' => 1];
        usort($entries, fn (array $a, array $b) => [(string) $b['at'], $rank[$b['type']]] <=> [(string) $a['at'], $rank[$a['type']]]);

        return response()->json(['success' => true, 'data' => ['points' => LoyaltyTransaction::balanceOf($member->id), 'entries' => array_slice($entries, 0, 200)]]);
    }

    public function storeQuizAnswers(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $max = (int) config('loyalty.quiz.max_answers_per_request');

        $data = $request->validate([
            'answers' => ['required', 'array', 'min:1', 'max:'.$max],
            'answers.*.id' => ['required', 'string', 'max:64'],
            'answers.*.play_id' => ['nullable', 'string', 'max:64'],
            'answers.*.quiz' => ['nullable', 'string', 'max:64'],
            'answers.*.question_id' => ['nullable', 'integer', 'min:0'],
            'answers.*.correct' => ['required', 'boolean'],
            'answers.*.timed_out' => ['nullable', 'boolean'],
            /* How long the player took, in seconds (0-20). The backend turns it into points (`LoyaltyPoints::forAnswer`). */
            'answers.*.seconds' => ['nullable', 'numeric', 'min:0', 'max:3600'],
            /* The stage the player is in AFTER this answer (a stage = one full pass over a section's questions). */
            'answers.*.stage' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'answers.*.answered_at' => ['nullable', 'date'],
            'platform' => ['nullable', 'string', 'max:16'],
        ]);

        $accepted = 0;
        $duplicates = 0;
        $overLimit = 0;
        $cap = (int) config('loyalty.quiz.plays_per_day');
        $tz = (string) config('app.timezone');
        /** @var array<string, array<string, true>> `quiz|day` => the plays already booked that day */
        $playsByDay = [];
        $touched = [];
        $newBadges = [];

        try {
            foreach ($data['answers'] as $answer) {
                $correct = (bool) $answer['correct'];
                // Stored on the app's clock, like every other timestamp, whatever zone the phone sent it in.
                $answeredAt = Carbon::parse($answer['answered_at'] ?? now())->timezone($tz);

                // A section allows `plays_per_day` plays a day: answers of a further play are not booked.
                if ($cap > 0 && ! empty($answer['quiz']) && ! empty($answer['play_id'])) {
                    $slot = $answer['quiz'].'|'.$answeredAt->toDateString();
                    $playsByDay[$slot] ??= $this->playsBooked($member->id, $answer['quiz'], $answeredAt);

                    if (! isset($playsByDay[$slot][$answer['play_id']])) {
                        if (count($playsByDay[$slot]) >= $cap) {
                            $overLimit++;

                            continue;
                        }
                        $playsByDay[$slot][$answer['play_id']] = true;
                    }
                }

                try {
                    LoyaltyTransaction::query()->create([
                        'user_id' => $member->id,
                        'source' => LoyaltyTransaction::SOURCE_QUIZ,
                        'client_id' => $answer['id'],
                        'play_id' => $answer['play_id'] ?? null,
                        'quiz_key' => $answer['quiz'] ?? null,
                        'question_id' => $answer['question_id'] ?? null,
                        'correct' => $correct,
                        'timed_out' => $correct ? false : (bool) ($answer['timed_out'] ?? false),
                        'answer_seconds' => isset($answer['seconds']) ? round(min(20.0, (float) $answer['seconds']), 2) : null,
                        'stage' => $answer['stage'] ?? null,
                        'points' => LoyaltyPoints::forAnswer($correct, isset($answer['seconds']) ? (float) $answer['seconds'] : null),
                        'answered_at' => $answeredAt,
                        'platform' => $data['platform'] ?? null,
                    ]);
                    $accepted++;
                    if (! empty($answer['quiz'])) {
                        $touched[$answer['quiz']] = true;
                    }
                } catch (QueryException $e) {
                    // The unique (user_id, client_id) index: this answer was booked by an earlier upload.
                    if (! $this->isDuplicate($e)) {
                        throw $e;
                    }
                    $duplicates++;
                }
            }

            foreach (array_keys($touched) as $quizKey) {
                array_push($newBadges, ...$this->badges->award($member->id, (string) $quizKey));
            }
        } catch (\Throwable $e) {
            Log::error('Loyalty quiz upload failed', ['user_id' => $member->id, 'count' => count($data['answers']), 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Could not save the answers.'], 500);
        }

        return response()->json(['success' => true, 'data' => ['accepted' => $accepted, 'duplicates' => $duplicates, 'over_daily_limit' => $overLimit, 'new_badges' => $newBadges] + $this->summary($member)]);
    }

    /** @return array<string, mixed> What the points are worth in pounds, the wallet and whether converting is open. */
    private function walletSummary(int $userId): array
    {
        $points = LoyaltyTransaction::balanceOf($userId);

        return [
            'egp_value' => LoyaltyWallet::egpValue($points),
            'convertible_egp' => LoyaltyWallet::wholeEgp($points),
            'wallet_balance' => WalletTransaction::balanceOf($userId),
            'wallet_enabled' => LoyaltyWallet::enabled(),
            'wallet_min_egp' => LoyaltyWallet::minEgp(),
            'wallet_min_topup' => WalletPayments::minTopup(),
            'wallet_membership_enabled' => WalletPayments::membershipEnabled(),
        ];
    }

    /** Turns points into pounds in the member's wallet: `egp` whole pounds, `id` made by the phone (a repeat books once). */
    public function convert(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $data = $request->validate([
            'egp' => ['required', 'integer', 'min:1', 'max:1000000'],
            'id' => ['required', 'string', 'max:64'],
        ]);

        try {
            $result = LoyaltyWallet::convert($member, (int) $data['egp'], 'wallet:'.$data['id']);
        } catch (\Throwable $e) {
            Log::error('Loyalty wallet conversion failed', ['user_id' => $member->id, 'egp' => $data['egp'], 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Could not convert the points.'], 500);
        }

        if (! $result['ok']) {
            return response()->json(['success' => false, 'reason' => $result['reason'], 'message' => 'The points could not be converted.'] + ['data' => $this->summary($member)], 422);
        }

        return response()->json(['success' => true, 'data' => ['converted_egp' => $result['egp'], 'spent_points' => $result['points'], 'duplicate' => $result['duplicate'] ?? false] + $this->summary($member)]);
    }

    /** Adds money to the wallet: opens a Paymob checkout for `amount` pounds. The wallet is credited only when the signed callback arrives. */
    public function topup(Request $request, \App\Actions\Payment\StartWalletTopup $start): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:'.\App\Actions\Payment\StartWalletTopup::MAX], 'return_url' => ['nullable', 'string', 'max:500']]);

        try {
            $result = $start->handle($member, (float) $data['amount'], $data['return_url'] ?? null, $request);
        } catch (\App\Services\Paymob\PaymobException $e) {
            Log::error('Wallet top-up could not be opened.', ['user_id' => $member->id, 'message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'The payment could not be opened. Please try again.'], 502);
        }

        return response()->json(['success' => true, 'data' => ['topup_id' => $result['topup']->id, 'amount' => (float) $result['topup']->amount, 'checkout_url' => $result['checkout_url'], 'client_secret' => $result['client_secret'], 'public_key' => $result['public_key'], 'form' => $result['form']]]);
    }

    /** The member's wallet: balance and its lines, newest first. */
    public function wallet(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $rows = WalletTransaction::query()->where('user_id', $member->id)->latest('id')->limit(100)->get();

        return response()->json(['success' => true, 'data' => [
            'balance' => WalletTransaction::balanceOf($member->id),
            'entries' => $rows->map(fn (WalletTransaction $w) => ['id' => $w->id, 'source' => $w->source, 'amount' => (float) $w->amount, 'points' => $w->points, 'note' => $w->note, 'at' => $w->created_at?->toIso8601String()])->all(),
        ] + $this->walletSummary($member->id)]);
    }

    /** @return array<int, array{up_to_seconds: int, points: int}> */
    private function speedTiers(): array
    {
        $tiers = (array) config('loyalty.quiz.speed_tiers');
        ksort($tiers);

        return collect($tiers)->map(fn ($points, $upTo) => ['up_to_seconds' => (int) $upTo, 'points' => (int) $points])->values()->all();
    }

    /** @return array<string, mixed> */
    private function summary(User $member): array
    {
        $rows = LoyaltyTransaction::query()->where('user_id', $member->id)->where('source', LoyaltyTransaction::SOURCE_QUIZ);

        return [
            'points' => LoyaltyTransaction::balanceOf($member->id),
            'right_answers' => (clone $rows)->where('correct', true)->count(),
            'wrong_answers' => (clone $rows)->where('correct', false)->count(),
            'points_right' => (int) config('loyalty.quiz.points_right'),
            'points_wrong' => (int) config('loyalty.quiz.points_wrong'),
            'speed_tiers' => $this->speedTiers(),
            'points_per_egp' => LoyaltyPoints::pointsPerEgp(),
        ] + $this->walletSummary($member->id) + $this->badges->catalog($member->id);
    }

    /**
     * The plays a member already has booked in one section on the day of `$at`.
     *
     * @return array<string, true>
     */
    private function playsBooked(int $userId, string $quizKey, Carbon $at): array
    {
        return LoyaltyTransaction::query()
            ->where('user_id', $userId)->where('source', LoyaltyTransaction::SOURCE_QUIZ)->where('quiz_key', $quizKey)
            ->whereNotNull('play_id')->whereBetween('answered_at', [$at->copy()->startOfDay(), $at->copy()->endOfDay()])
            ->distinct()->pluck('play_id')->mapWithKeys(fn ($id) => [(string) $id => true])->all();
    }

    private function isDuplicate(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505'], true);
    }
}
