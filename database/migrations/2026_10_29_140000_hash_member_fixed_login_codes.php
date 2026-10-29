<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * A member's fixed login code is stored HASHED, may expire, and records who set it.
 * The old plain `otp_fixed_code` values are hashed into the new column and wiped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp_fixed_code_hash')->nullable()->after('otp_fixed_code');
            $table->unsignedTinyInteger('otp_fixed_code_length')->nullable()->after('otp_fixed_code_hash');
            $table->timestamp('otp_fixed_code_expires_at')->nullable()->after('otp_fixed_code_length');
            $table->unsignedBigInteger('otp_fixed_code_set_by')->nullable()->after('otp_fixed_code_expires_at');
        });

        DB::table('users')->whereNotNull('otp_fixed_code')->where('otp_fixed_code', '!=', '')->orderBy('id')->each(function ($user) {
            $code = preg_replace('/\D+/', '', (string) $user->otp_fixed_code) ?? '';

            DB::table('users')->where('id', $user->id)->update([
                'otp_fixed_code_hash' => $code === '' ? null : Hash::make($code),
                'otp_fixed_code_length' => $code === '' ? null : strlen($code),
                'otp_fixed_code' => null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['otp_fixed_code_hash', 'otp_fixed_code_length', 'otp_fixed_code_expires_at', 'otp_fixed_code_set_by']);
        });
    }
};
