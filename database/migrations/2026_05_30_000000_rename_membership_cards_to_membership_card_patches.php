<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('membership_card_patches') && Schema::hasTable('membership_cards')) {
            Schema::dropIfExists('membership_card_patches');
        }

        if (Schema::hasTable('membership_cards')) {
            Schema::rename('membership_cards', 'membership_card_patches');
        }

        $this->renamePermission('manage membership cards', 'manage membership card patches');
        $this->renamePermission('manage own membership cards', 'manage own membership card patches');
        $this->renamePermission('manage partner membership cards', 'manage partner membership card patches');
    }

    public function down(): void
    {
        if (Schema::hasTable('membership_cards') && Schema::hasTable('membership_card_patches')) {
            Schema::dropIfExists('membership_cards');
        }

        if (Schema::hasTable('membership_card_patches')) {
            Schema::rename('membership_card_patches', 'membership_cards');
        }

        $this->renamePermission('manage membership card patches', 'manage membership cards');
        $this->renamePermission('manage own membership card patches', 'manage own membership cards');
        $this->renamePermission('manage partner membership card patches', 'manage partner membership cards');
    }

    private function renamePermission(string $oldName, string $newName): void
    {
        $existing = DB::table('permissions')
            ->where('name', $newName)
            ->first();

        if ($existing) {
            DB::table('permissions')
                ->where('name', $oldName)
                ->delete();
        } else {
            DB::table('permissions')
                ->where('name', $oldName)
                ->update(['name' => $newName]);
        }
    }
};