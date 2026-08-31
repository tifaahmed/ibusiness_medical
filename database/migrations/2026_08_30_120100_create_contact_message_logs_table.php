<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail for an enquiry: what changed, who changed it, and what it
 * was before.
 *
 * Same shape as `order_logs` and `facility_logs`, with one difference that
 * matters — `admin_id` is nullable, because the opening `received` row is
 * written by a visitor filling in a public form and there is no admin behind
 * it.
 *
 * Values are stored raw, never as labels: a label is written in whatever
 * language the change was made in and would then read that way forever, in a
 * log somebody else opens in the other language.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->index(['contact_message_id', 'id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_message_logs');
    }
};
