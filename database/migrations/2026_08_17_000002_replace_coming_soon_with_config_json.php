<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->json('coming_soon_config')->nullable()->default(null)->after('discount_percent');
        });

        // Migrate existing boolean values into the new JSON column
        $facilities = DB::table('facilities')
            ->where('coming_soon', true)
            ->select('id')
            ->get();

        foreach ($facilities as $facility) {
            DB::table('facilities')
                ->where('id', $facility->id)
                ->update([
                    'coming_soon_config' => json_encode([
                        'enabled' => true,
                        'message_ar' => 'قريباً',
                        'message_en' => 'COMING SOON',
                        'text_color' => '#ffffff',
                        'bg_color' => '#dc2626',
                        'font_size' => 15,
                    ]),
                ]);
        }

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('coming_soon');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->boolean('coming_soon')->default(false)->after('discount_percent');
        });

        $facilities = DB::table('facilities')
            ->whereNotNull('coming_soon_config')
            ->select('id', 'coming_soon_config')
            ->get();

        foreach ($facilities as $facility) {
            $config = json_decode($facility->coming_soon_config, true);
            DB::table('facilities')
                ->where('id', $facility->id)
                ->update([
                    'coming_soon' => ($config['enabled'] ?? false),
                ]);
        }

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('coming_soon_config');
        });
    }
};
