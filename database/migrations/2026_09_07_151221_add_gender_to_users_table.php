<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member's gender, which the storefront's registration form asks for.
 *
 * On `users` rather than `memberships` because it describes the person, not
 * the card: a member who is issued a second card does not answer this twice.
 *
 * Nullable, and it stays nullable. Every field on the registration form is
 * optional by design — somebody signing in with their phone is let in first and
 * asked for details afterwards — and half the rows in this table predate the
 * question being asked at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 16)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
