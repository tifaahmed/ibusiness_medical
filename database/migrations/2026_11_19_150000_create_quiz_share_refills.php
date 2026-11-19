<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A member who shares their referral code gets today's quiz plays back, on every section. One row per refill. ADD ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quiz_share_refills')) {
            Schema::create('quiz_share_refills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('day');
                $table->timestamp('created_at')->nullable();
                $table->index(['user_id', 'day']);
            });
        }

        if (! \App\Models\Setting::query()->where('slug', 'quiz_share_refills_per_day')->exists()) {
            \App\Models\Setting::query()->create([
                'slug' => 'quiz_share_refills_per_day',
                'name' => ['en' => 'Quiz: refills a member may earn per day by sharing their referral code (0 = off)', 'ar' => 'اللعبة: كم مرة في اليوم تتجدد المحاولات بمشاركة كود الإحالة (0 = متوقف)'],
                'value' => '1',
                'value_type' => 'number',
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally empty: never drop tables that hold data.
    }
};
