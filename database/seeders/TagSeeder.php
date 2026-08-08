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
            ['name' => 'Premium',     'icon' => '💎', 'color' => '#8B5CF6'],
            ['name' => 'New',         'icon' => '🆕', 'color' => '#3B82F6'],
            ['name' => 'Trending',    'icon' => '🚀', 'color' => '#EF4444'],
            ['name' => 'Top Rated',   'icon' => '💯', 'color' => '#F59E0B'],
            ['name' => 'Best Offer',  'icon' => '🎁', 'color' => '#10B981'],
            ['name' => 'Sale',        'icon' => '🏷️', 'color' => '#EC4899'],
            ['name' => 'Featured',    'icon' => '⭐', 'color' => '#F59E0B'],
            ['name' => 'Limited',     'icon' => '⏳', 'color' => '#6B7280'],
            ['name' => 'Exclusive',   'icon' => '💎', 'color' => '#8B5CF6'],
            ['name' => 'Popular',     'icon' => '🔥', 'color' => '#EF4444'],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['name' => $tag['name']],
                [
                    'icon' => $tag['icon'],
                    'color' => $tag['color'],
                    'created_by' => $createdBy,
                ]
            );
        }
    }
}
