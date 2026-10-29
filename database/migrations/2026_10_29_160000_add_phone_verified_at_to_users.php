<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A phone is VERIFIED once a real SMS code sent to it was typed back. A relative's phone is typed in by the
 * primary member and stays unverified until the relative's first sign-in code. Every current member who is not
 * a relative was checked with the customer before, so their phone starts verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('memberships')
                ->whereColumn('memberships.user_id', 'users.id')
                ->whereNull('memberships.deleted_at')
                ->where(fn ($m) => $m->whereNull('memberships.member_type')->orWhere('memberships.member_type', '!=', 'relative')))
            ->update(['phone_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }
};
