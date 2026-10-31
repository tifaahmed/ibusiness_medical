<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member's money wallet, one row per credit (+) or debit (-) in Egyptian pounds. Append-only: the balance is the SUM of
     * `amount`, so a row is never edited. Today the only credit is a conversion of loyalty points (`source = points`), which
     * is also written to `loyalty_transactions` as a negative row (`source = wallet`) with the same `client_id`, in one DB transaction.
     * `client_id` is made by the phone for each conversion and is unique per member, so a repeated request converts once.
     */
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 24)->default('points')->index();
            $table->string('client_id', 64);
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('points')->nullable();
            $table->decimal('points_per_egp', 12, 4)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
