<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('card_x', 8, 2)->nullable();
            $table->decimal('card_y', 8, 2)->nullable();
            $table->decimal('card_scale', 5, 3)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_by');
        });

        // users.partner_id is declared in create_users_table without a constraint
        // because the two tables reference each other; wire it up now.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
        });

        Schema::dropIfExists('partners');
    }
};
