<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The quiz ranking: everyone who has played, ordered by the loyalty balance they hold NOW (the SUM of their ledger), so a
 * member who spends points drops down by themselves. Names are shown as first name + the first letter of the last one.
 * Players with the same balance share a rank (1, 2, 2, 4...). Computed here only; the apps just draw it.
 */
class QuizLeaderboard
{
    public const LIMIT = 100;

    /** @return list<array{rank: int, name: string, points: int, me: bool}> */
    public function top(?int $meId = null): array
    {
        $rows = $this->balances()->orderByDesc('points')->orderBy('user_id')->limit(self::LIMIT)->get();
        $out = [];
        $rank = 0;
        $last = null;

        foreach ($rows as $i => $row) {
            $points = (int) $row->points;

            if ($points !== $last) {
                $rank = $i + 1;
                $last = $points;
            }

            $out[] = ['rank' => $rank, 'name' => self::display((string) $row->name), 'points' => $points, 'me' => $meId !== null && (int) $row->user_id === $meId];
        }

        return $out;
    }

    /** @return array{rank: int|null, points: int, players: int}|null null when the member has never played */
    public function mine(User $member): ?array
    {
        $played = LoyaltyTransaction::query()->where('user_id', $member->id)->where('source', LoyaltyTransaction::SOURCE_QUIZ)->exists();

        if (! $played) {
            return null;
        }

        $points = (int) LoyaltyTransaction::query()->where('user_id', $member->id)->sum('points');
        $above = DB::query()->fromSub($this->balances(), 'b')->where('points', '>', $points)->count();

        return ['rank' => $points > 0 ? $above + 1 : null, 'points' => $points, 'players' => $this->balances()->get()->where('points', '>', 0)->count()];
    }

    /** One row per player: user_id, name, current balance. Only users who played the quiz at least once. */
    private function balances()
    {
        $players = LoyaltyTransaction::query()->where('source', LoyaltyTransaction::SOURCE_QUIZ)->select('user_id')->distinct();

        return LoyaltyTransaction::query()
            ->join('users', 'users.id', '=', 'loyalty_transactions.user_id')
            ->whereIn('loyalty_transactions.user_id', $players)
            ->groupBy('loyalty_transactions.user_id', 'users.name')
            ->havingRaw('SUM(loyalty_transactions.points) > 0')
            ->selectRaw('loyalty_transactions.user_id as user_id, users.name as name, SUM(loyalty_transactions.points) as points');
    }

    /** «محمد أحمد علي» → «محمد ع.» */
    public static function display(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) === 0) {
            return '—';
        }

        return count($parts) === 1 ? $parts[0] : $parts[0].' '.mb_substr(end($parts), 0, 1).'.';
    }
}
