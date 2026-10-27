<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The General-knowledge and History sections (added to config/loyalty.php after the Fawazeer one) get the default
     * badges too, on a database that already ran the earlier seeding. Only ADDS, and never touches a badge an admin has already edited (the unique key skips them).
     */
    public function up(): void
    {
        if (! Schema::hasTable('quiz_badge_definitions')) {
            return;
        }

        $icons = ['solved' => '🎯', 'streak' => '🔥', 'stage' => '🏆'];

        foreach ((array) config('loyalty.quiz.sections') as $quizKey) {
            foreach ((array) config('loyalty.quiz.badges') as $kind => $thresholds) {
                foreach ($thresholds as $threshold => $reward) {
                    DB::table('quiz_badge_definitions')->insertOrIgnore([
                        'quiz_key' => $quizKey, 'kind' => $kind, 'threshold' => $threshold, 'title' => config("loyalty.quiz.badge_titles.{$kind}.{$threshold}"), 'icon' => $icons[$kind] ?? '🏅',
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
