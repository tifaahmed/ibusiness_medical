<?php

namespace Database\Seeders;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $createdBy = $admin?->id ?? 1;

        $tags = [
            ['ar' => 'مميز', 'en' => 'Premium', 'icon' => '💎', 'color' => '#8B5CF6'],
            ['ar' => 'جديد', 'en' => 'New', 'icon' => '🆕', 'color' => '#3B82F6'],
            ['ar' => 'الأكثر رواجًا', 'en' => 'Trending', 'icon' => '🚀', 'color' => '#EF4444'],
            ['ar' => 'الأعلى تقييمًا', 'en' => 'Top Rated', 'icon' => '💯', 'color' => '#F59E0B'],
            ['ar' => 'أفضل عرض', 'en' => 'Best Offer', 'icon' => '🎁', 'color' => '#10B981'],
            ['ar' => 'تخفيضات', 'en' => 'Sale', 'icon' => '🏷️', 'color' => '#EC4899'],
            ['ar' => 'مختار', 'en' => 'Featured', 'icon' => '⭐', 'color' => '#F59E0B'],
            ['ar' => 'كمية محدودة', 'en' => 'Limited', 'icon' => '⏳', 'color' => '#6B7280'],
            ['ar' => 'حصري', 'en' => 'Exclusive', 'icon' => '💎', 'color' => '#8B5CF6'],
            ['ar' => 'الأكثر طلبًا', 'en' => 'Popular', 'icon' => '🔥', 'color' => '#EF4444'],
        ];

        foreach ($tags as $tag) {
            /*
             * Matched on the English name rather than through `updateOrCreate`:
             * the name is a json map now, and an attribute array that reads
             * `name->en` cannot also be the payload a new row is created from.
             */
            $existing = Tag::query()->where('name->en', $tag['en'])->first();

            $attributes = [
                'name' => ['ar' => $tag['ar'], 'en' => $tag['en']],
                'icon' => $tag['icon'],
                'color' => $tag['color'],
                'created_by' => $createdBy,
            ];

            if ($existing === null) {
                Tag::create($attributes);

                continue;
            }

            $existing->update($attributes);
        }
    }
}
