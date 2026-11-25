<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Only a row still on the old default moves: a wording an admin typed is left alone. */
    public function up(): void
    {
        DB::table('settings')
            ->where('slug', 'otp_message')
            ->where('value', 'Your code is :code')
            ->update(['value' => 'Your code from iBusiness is :code', 'updated_at' => now()]);

        Cache::forget('settings.all');
    }

    public function down(): void {}
};
