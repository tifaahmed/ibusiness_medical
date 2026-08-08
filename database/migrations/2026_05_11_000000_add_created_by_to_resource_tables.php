<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that get a `created_by` FK so the `manage own X` permissions can
     * scope row access. Each resource has its own list/edit/delete controllers
     * that consult this column via the CreatorScoped trait.
     */
    private const TABLES = [
        'offers',
        'contracts',
        'companies',
        'facilities',
        'facility_branches',
        'facility_types',
        'governorates',
        'contact_messages',
        'faqs',
        'partners',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'created_by')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('created_by')->nullable()->after('id')
                    ->constrained('users')->nullOnDelete();
                $t->index('created_by');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'created_by')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['created_by']);
                $t->dropIndex(['created_by']);
                $t->dropColumn('created_by');
            });
        }
    }
};
