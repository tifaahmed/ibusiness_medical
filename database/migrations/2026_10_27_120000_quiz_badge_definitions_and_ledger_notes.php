<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Badges become admin data (`quiz_badge_definitions`: threshold, title, icon, reward points, on/off), seeded once from
     * config/loyalty.php. Earned badges point at their definition and keep the reward they paid. The ledger learns
     * where a row came from (`source`: quiz | badge | admin), who granted it and why. Only ADDS.
     */
    public function up(): void
    {
        if (! Schema::hasTable('quiz_badge_definitions')) {
            Schema::create('quiz_badge_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('quiz_key', 64);
                $table->string('kind', 16);
                $table->unsignedInteger('threshold');
                $table->string('title', 120)->nullable();
                $table->string('icon', 16)->nullable();
                $table->unsignedInteger('reward_points')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['quiz_key', 'kind', 'threshold']);
            });

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

        if (Schema::hasTable('quiz_badges')) {
            Schema::table('quiz_badges', function (Blueprint $table) {
                if (! Schema::hasColumn('quiz_badges', 'definition_id')) {
                    $table->foreignId('definition_id')->nullable()->after('user_id')->constrained('quiz_badge_definitions')->nullOnDelete();
                }
                if (! Schema::hasColumn('quiz_badges', 'reward_points')) {
                    $table->unsignedInteger('reward_points')->default(0)->after('badge_key');
                }
            });

            // Badges earned before definitions existed: link them by their key; no reward was paid for them.
            foreach (DB::table('quiz_badge_definitions')->get() as $definition) {
                DB::table('quiz_badges')->whereNull('definition_id')->where('quiz_key', $definition->quiz_key)
                    ->where('badge_key', "{$definition->kind}_{$definition->threshold}")->update(['definition_id' => $definition->id]);
            }
        }

        if (Schema::hasTable('loyalty_transactions')) {
            Schema::table('loyalty_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('loyalty_transactions', 'note')) {
                    $table->string('note', 255)->nullable()->after('points');
                }
                if (! Schema::hasColumn('loyalty_transactions', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('note')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('loyalty_transactions', 'badge_definition_id')) {
                    $table->foreignId('badge_definition_id')->nullable()->after('created_by')->constrained('quiz_badge_definitions')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        // Left in place: dropping these would lose the badge rules and the audit trail of granted points.
    }
};
