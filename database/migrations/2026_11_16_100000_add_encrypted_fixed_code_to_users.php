<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member's fixed login code is still checked against its HASH. This only ADDS an encrypted copy
 * (Laravel Crypt, APP_KEY) so the super admin can read back the code they typed. Existing codes have
 * no copy (hash only) and stay as they are until a new one is typed. Nothing is dropped or rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'otp_fixed_code_encrypted')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('otp_fixed_code_encrypted')->nullable()->after('otp_fixed_code_hash');
            });
        }
    }

    public function down(): void
    {
        // Additive only: the column is left in place.
    }
};
