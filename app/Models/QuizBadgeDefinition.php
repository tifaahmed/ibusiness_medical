<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A badge as the admin defines it (/admin/loyalty): what earns it (`kind` + `threshold`), how it looks and how many
 * loyalty points it pays the member when they earn it. Never deleted — turn it off, so the badges members already
 * hold keep their meaning.
 */
class QuizBadgeDefinition extends Model
{
    public const KINDS = ['solved', 'streak', 'stage'];

    protected $fillable = ['quiz_key', 'kind', 'threshold', 'title', 'icon', 'reward_points', 'is_active'];

    protected function casts(): array
    {
        return ['threshold' => 'integer', 'reward_points' => 'integer', 'is_active' => 'boolean'];
    }

    public function earned(): HasMany
    {
        return $this->hasMany(QuizBadge::class, 'definition_id');
    }

    /** `solved_100`: the key the apps use. */
    public function key(): string
    {
        return "{$this->kind}_{$this->threshold}";
    }
}
