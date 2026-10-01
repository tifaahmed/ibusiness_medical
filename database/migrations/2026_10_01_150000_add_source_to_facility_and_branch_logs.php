<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which tool wrote a log entry (`App\Support\FacilityAudit::SOURCE_*`);
     * null for an admin form save. FacilityLog::record() and
     * FacilityBranchLog::record() have written this column since the audit
     * observer landed, but the column itself was never added — so every
     * facility save failed with "Unknown column 'source'" and was rolled back.
     */
    private array $tables = ['facility_logs', 'facility_branch_logs'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasColumn($name, 'source')) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) {
                $table->string('source', 32)->nullable()->after('action')->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasColumn($name, 'source')) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            });
        }
    }
};
