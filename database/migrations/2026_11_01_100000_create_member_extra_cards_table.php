<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra cards held by one member (primary or relative): plastic ("physical") or digital ("soft").
 * ADD-ONLY: a new table, nothing existing is touched. `card_number` NULL = the card follows the member's own number
 * (so renumbering the member renumbers it); a value = a different number that opens the SAME member.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_extra_cards')) {
            return;
        }

        Schema::create('member_extra_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
            $table->string('kind', 16); // physical | soft
            $table->string('card_number', 64)->nullable()->index();
            $table->string('source', 16)->default('admin'); // admin | order
            $table->string('status', 16)->default('active'); // requested (ordered, not yet confirmed) | active | cancelled
            $table->unsignedBigInteger('parent_card_id')->nullable()->index(); // a soft copy points at the physical card it mirrors
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_extra_cards');
    }
};
