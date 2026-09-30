<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A facility is never dropped outright by the admin screens: it, its
     * branches and its managers are soft-deleted (with who did it) so the
     * audit trail survives and an admin can restore them.
     */
    private array $tables = ['facilities', 'facility_branches', 'facility_managers'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasColumn($name, 'deleted_at')) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('deleted_by');
                $table->dropSoftDeletes();
            });
        }
    }
};
