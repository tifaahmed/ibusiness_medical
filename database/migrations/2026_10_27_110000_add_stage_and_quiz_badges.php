<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The quiz's badges (earned once per member and section; a row is never removed) and the stage a player was in
     * when an answer was given (nullable: answers booked before stages existed have none).
     */
    public function up(): void
    {
        if (Schema::hasTable('loyalty_transactions') && ! Schema::hasColumn('loyalty_transactions', 'stage')) {
            Schema::table('loyalty_transactions', function (Blueprint $table) {
                $table->unsignedSmallInteger('stage')->nullable()->after('timed_out');
            });
        }

        if (! Schema::hasTable('quiz_badges')) {
            Schema::create('quiz_badges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('quiz_key', 64);
                $table->string('badge_key', 48);
                $table->timestamp('earned_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'quiz_key', 'badge_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_badges');
    }
};
