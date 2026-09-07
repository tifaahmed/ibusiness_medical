<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A login code that belongs to one member rather than to the whole site.
 *
 * The site-wide switches in `settings` are all-or-nothing: either every member
 * gets a real code by SMS or nobody does. That is too blunt for the cases this
 * is actually for — a tester who needs to sign in over and over, a member whose
 * number no longer receives messages, somebody being walked through the app on
 * a call. Filling this in exempts that one member and leaves the rest of the
 * site on whatever the settings say.
 *
 * Null means "follow the site setting", which is what every existing row gets
 * and what makes this column invisible until somebody deliberately uses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eight is the longest code OtpSettings will generate, so a
            // per-member one can never be longer than a real one.
            $table->string('otp_fixed_code', 8)
                ->nullable()
                ->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('otp_fixed_code');
        });
    }
};
