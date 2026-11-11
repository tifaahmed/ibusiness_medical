<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New badge ideas (plays, perfect, fast, days) for every section. Only ADDS: the unique key skips any badge that already
     * exists, so nothing an admin edited is touched.
     */
    public function up(): void
    {
        if (! Schema::hasTable('quiz_badge_definitions')) {
            return;
        }

        $icons = ['plays' => '🎮', 'perfect' => '💯', 'fast' => '⚡', 'days' => '📅'];

        foreach ((array) config('loyalty.quiz.sections') as $quizKey) {
            foreach ($icons as $kind => $icon) {
                foreach ((array) config("loyalty.quiz.badges.{$kind}") as $threshold => $reward) {
                    DB::table('quiz_badge_definitions')->insertOrIgnore([
                        'quiz_key' => $quizKey, 'kind' => $kind, 'threshold' => $threshold, 'title' => config("loyalty.quiz.badge_titles.{$kind}.{$threshold}"), 'icon' => $icon,
                        'reward_points' => $reward, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Left in place: removing badge rules could orphan badges members hold.
    }
};
