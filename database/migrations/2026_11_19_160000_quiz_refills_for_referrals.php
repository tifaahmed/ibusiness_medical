<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A refill can now also come from someone registering under the member's referral code. ADD ONLY. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quiz_share_refills', 'reason')) {
            Schema::table('quiz_share_refills', function (Blueprint $table) {
                $table->string('reason', 16)->default('share'); // share | referral
                $table->unsignedBigInteger('referral_id')->nullable()->unique(); // one refill per referral, ever
            });
        }

        if (! \App\Models\Setting::query()->where('slug', 'quiz_referral_refills_per_day')->exists()) {
            \App\Models\Setting::query()->create([
                'slug' => 'quiz_referral_refills_per_day',
                'name' => ['en' => 'Quiz: refills a member earns per day when people register under their referral code (0 = off)', 'ar' => 'اللعبة: كم تجديد في اليوم بسبب تسجيل أشخاص بكود الإحالة (0 = متوقف)'],
                'value' => '5',
                'value_type' => 'number',
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally empty.
    }
};
