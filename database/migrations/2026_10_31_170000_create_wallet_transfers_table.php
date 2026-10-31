<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A request to send loyalty points or wallet money (`kind` = points | wallet) to another member. Nothing moves when it is made:
     * the money/points move only when the RECIPIENT accepts (and only if the sender still has enough then). A request can be
     * declined by the recipient, cancelled by the sender, or expire. `client_id` (unique per sender) makes a repeated request book once.
     */
    public function up(): void
    {
        if (Schema::hasTable('wallet_transfers')) {
            return;
        }

        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 12);
            $table->decimal('amount', 12, 2);
            $table->string('status', 12)->default('pending')->index();
            $table->string('client_id', 64);
            $table->string('note', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['from_user_id', 'client_id']);
            $table->index(['to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transfers');
    }
};
