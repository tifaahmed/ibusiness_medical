<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loyalty points, one row per thing that earned or cost them (today: a quiz answer). The balance is the SUM of
     * `points`, so a row is never edited. `client_id` is made by the phone when the answer is given (the phone may be
     * offline and send it much later, more than once): it is unique per member, which makes the upload idempotent.
     */
    public function up(): void
    {
        if (Schema::hasTable('loyalty_transactions')) {
            return;
        }

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 24)->default('quiz')->index();
            $table->string('client_id', 64);
            $table->string('play_id', 64)->nullable();
            $table->string('quiz_key', 64)->nullable();
            $table->unsignedInteger('question_id')->nullable();
            $table->boolean('correct')->default(false);
            $table->boolean('timed_out')->default(false);
            $table->integer('points');
            $table->timestamp('answered_at')->nullable();
            $table->string('platform', 16)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
