<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->json('name_i18n')->nullable()->after('name');
        });

        DB::table('service_types')->select('id', 'name')->orderBy('id')->get()->each(function ($row) {
            DB::table('service_types')->where('id', $row->id)->update([
                'name_i18n' => json_encode(['en' => $row->name, 'ar' => $row->name], JSON_UNESCAPED_UNICODE),
            ]);
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->renameColumn('name_i18n', 'name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->string('name_plain')->nullable()->after('name');
        });

        DB::table('service_types')->select('id', 'name')->orderBy('id')->get()->each(function ($row) {
            $decoded = json_decode($row->name, true);
            $value = is_array($decoded) ? ($decoded['en'] ?? $decoded['ar'] ?? '') : (string) $row->name;
            DB::table('service_types')->where('id', $row->id)->update([
                'name_plain' => $value,
            ]);
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->renameColumn('name_plain', 'name');
        });
    }
};
