<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One earning (+) or loss (-) of loyalty points: a quiz answer, a badge reward or a grant by an admin. Append-only: the balance is the sum. */
class LoyaltyTransaction extends Model
{
    public const SOURCE_QUIZ = 'quiz';

    public const SOURCE_BADGE = 'badge';

    public const SOURCE_ADMIN = 'admin';

    /** Points spent on a conversion into the money wallet (negative). */
    public const SOURCE_WALLET = 'wallet';

    /** Points sent to / received from another member (a debit and a credit that share a transfer). */
    public const SOURCE_TRANSFER = 'transfer';

    protected $fillable = ['user_id', 'source', 'client_id', 'play_id', 'quiz_key', 'question_id', 'correct', 'timed_out', 'answer_seconds', 'stage', 'points', 'note', 'created_by', 'badge_definition_id', 'answered_at', 'platform'];

    protected function casts(): array
    {
        return [
            'correct' => 'boolean',
            'timed_out' => 'boolean',
            'points' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** A member's balance, from every source. May be negative. */
    public static function balanceOf(int $userId): int
    {
        return (int) static::query()->where('user_id', $userId)->sum('points');
    }
}
