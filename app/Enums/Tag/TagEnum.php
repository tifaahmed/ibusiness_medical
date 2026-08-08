<?php

namespace App\Enums\Tag;

class TagEnum
{
    /**
     * Get icon options for select dropdowns.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getIconOptions(): array
    {
        return [
            ['value' => '🏷️', 'label' => '🏷️ Tag'],
            ['value' => '⭐', 'label' => '⭐ Star'],
            ['value' => '🔥', 'label' => '🔥 Fire'],
            ['value' => '🆕', 'label' => '🆕 New'],
            ['value' => '💎', 'label' => '💎 Premium'],
            ['value' => '🎉', 'label' => '🎉 Celebration'],
            ['value' => '✅', 'label' => '✅ Check'],
            ['value' => '⏳', 'label' => '⏳ Soon'],
            ['value' => '🎁', 'label' => '🎁 Offer'],
            ['value' => '📢', 'label' => '📢 Announcement'],
            ['value' => '💯', 'label' => '💯 Top'],
            ['value' => '🚀', 'label' => '🚀 Trending'],
        ];
    }

    /**
     * Get color options for select dropdowns.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getColorOptions(): array
    {
        return [
            ['value' => '#EF4444', 'label' => 'Red'],
            ['value' => '#3B82F6', 'label' => 'Blue'],
            ['value' => '#10B981', 'label' => 'Green'],
            ['value' => '#8B5CF6', 'label' => 'Purple'],
            ['value' => '#F59E0B', 'label' => 'Amber'],
            ['value' => '#06B6D4', 'label' => 'Cyan'],
            ['value' => '#EC4899', 'label' => 'Pink'],
            ['value' => '#6B7280', 'label' => 'Gray'],
        ];
    }
}
