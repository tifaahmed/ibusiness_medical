<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores get what facilities already have: a sales representative and the
 * people to contact. The signed contract is a `contract` media collection, so
 * it needs no column. Additive only; guarded so a re-run is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stores', 'sales_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->foreignId('sales_id')->nullable()->after('slug')
                    ->constrained('sales')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('store_managers')) {
            Schema::create('store_managers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('position')->nullable();
                /* [{ number, type }] — the same shape as a branch's phones. */
                $table->json('phones')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                /* Removing a card from the form soft-deletes it, so it can be recovered. */
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_managers');

        if (Schema::hasColumn('stores', 'sales_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sales_id');
            });
        }
    }
};
