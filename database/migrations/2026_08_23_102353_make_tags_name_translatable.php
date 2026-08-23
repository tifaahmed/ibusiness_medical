<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tag names become translatable, the same way product type names already are:
 * a json column read through `HasTranslations`.
 *
 * The storefront resolves a tag's name for the language its visitor is reading,
 * so an English-only tag column meant an Arabic shop sidebar in English.
 *
 * The column is replaced rather than converted in place: the existing values
 * are short English names that have to be lifted into `{"ar": …, "en": …}`, and
 * a `string(255)` column has no room for the json wrapper around a long one.
 */
return new class extends Migration
{
    /**
     * Arabic for the names the tag seeder ships, so the ten tags already in the
     * catalogue read correctly on day one instead of showing English in both
     * languages until somebody edits each of them by hand.
     *
     * @var array<string, string>
     */
    private const SEEDED_ARABIC = [
        'Premium' => 'مميز',
        'New' => 'جديد',
        'Trending' => 'الأكثر رواجًا',
        'Top Rated' => 'الأعلى تقييمًا',
        'Best Offer' => 'أفضل عرض',
        'Sale' => 'تخفيضات',
        'Featured' => 'مختار',
        'Limited' => 'كمية محدودة',
        'Exclusive' => 'حصري',
        'Popular' => 'الأكثر طلبًا',
    ];

    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->json('name_translations')->nullable()->after('name');
        });

        DB::table('tags')->orderBy('id')->select(['id', 'name'])->chunk(200, function ($tags) {
            foreach ($tags as $tag) {
                $name = (string) $tag->name;

                DB::table('tags')->where('id', $tag->id)->update([
                    'name_translations' => json_encode([
                        'ar' => self::SEEDED_ARABIC[$name] ?? $name,
                        'en' => $name,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->renameColumn('name_translations', 'name');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('name_single')->nullable()->after('name');
        });

        DB::table('tags')->orderBy('id')->select(['id', 'name'])->chunk(200, function ($tags) {
            foreach ($tags as $tag) {
                $translations = json_decode((string) $tag->name, true);

                DB::table('tags')->where('id', $tag->id)->update([
                    'name_single' => is_array($translations)
                        ? ($translations['en'] ?? $translations['ar'] ?? '')
                        : (string) $tag->name,
                ]);
            }
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->renameColumn('name_single', 'name');
        });
    }
};
