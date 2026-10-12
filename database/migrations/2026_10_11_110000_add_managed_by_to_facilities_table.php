<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('facilities', 'managed_by')) {
            return;
        }

        Schema::table('facilities', function (Blueprint $table) {
            // The admin user who looks after this facility. Optional; clears
            // itself if that user is ever removed for good.
            $table->foreignId('managed_by')->nullable()->after('sales_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('facilities', 'managed_by')) {
            return;
        }

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('managed_by');
        });
    }
};
