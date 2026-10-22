<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finding a member by phone compared the digits of EVERY row
 * (REPLACE(REPLACE(…phone…)) IN (…)), so each sign-in, phone-check and trial
 * read the whole users table. This keeps those digits in a stored column the
 * database maintains by itself — no code writes it — and indexes it, so the
 * same lookup becomes an index hit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || Schema::hasColumn('users', 'phone_digits')) {
            return;
        }

        DB::statement("ALTER TABLE users ADD COLUMN phone_digits VARCHAR(40) GENERATED ALWAYS AS (REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '')) STORED, ADD INDEX users_phone_digits_index (phone_digits)");
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'phone_digits')) {
            DB::statement('ALTER TABLE users DROP INDEX users_phone_digits_index, DROP COLUMN phone_digits');
        }
    }
};
