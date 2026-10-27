<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A badge a member earned in one quiz section. Append-only. */
class QuizBadge extends Model
{
    protected $fillable = ['user_id', 'definition_id', 'quiz_key', 'badge_key', 'reward_points', 'earned_at'];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime', 'reward_points' => 'integer'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(QuizBadgeDefinition::class, 'definition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
