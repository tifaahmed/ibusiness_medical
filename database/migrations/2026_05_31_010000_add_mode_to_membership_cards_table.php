<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('membership_cards')) {
            return;
        }

        if (Schema::hasColumn('membership_cards', 'mode')) {
            return;
        }

        Schema::table('membership_cards', function (Blueprint $table) {
            $table->string('mode', 16)->default('full');
        });

        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $sm->listTableIndexes('membership_cards');
        $hasOldUnique = isset($indexes['membership_cards_membership_id_unique']) || isset($indexes['primary']);

        foreach ($indexes as $name => $index) {
            if ($index->isUnique() && !$index->isPrimary()) {
                $columns = $index->getColumns();
                if ($columns === ['membership_id']) {
                    Schema::table('membership_cards', function (Blueprint $table) {
                        $table->dropUnique(['membership_id']);
                    });
                }
            }
        }

        Schema::table('membership_cards', function (Blueprint $table) {
            $table->unique(['membership_id', 'mode']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('membership_cards')) {
            return;
        }

        if (!Schema::hasColumn('membership_cards', 'mode')) {
            return;
        }

        $sm = Schema::getConnection()->getDoctrineSchemaManager();
        $indexes = $sm->listTableIndexes('membership_cards');

        foreach ($indexes as $name => $index) {
            if ($index->isUnique() && !$index->isPrimary()) {
                $columns = $index->getColumns();
                if (in_array('membership_id', $columns) && in_array('mode', $columns)) {
                    Schema::table('membership_cards', function (Blueprint $table) {
                        $table->dropUnique(['membership_id', 'mode']);
                    });
                }
            }
        }

        if (!isset($indexes['membership_cards_membership_id_unique'])) {
            Schema::table('membership_cards', function (Blueprint $table) {
                $table->unique('membership_id');
            });
        }

        Schema::table('membership_cards', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};