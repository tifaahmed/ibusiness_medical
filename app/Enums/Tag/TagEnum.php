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
            ['value' => '🔖', 'label' => '🔖 Bookmark'],
            ['value' => '📌', 'label' => '📌 Pin'],
            ['value' => '📍', 'label' => '📍 Location'],
            ['value' => '⭐', 'label' => '⭐ Star'],
            ['value' => '🌟', 'label' => '🌟 Glowing Star'],
            ['value' => '❤️', 'label' => '❤️ Heart'],
            ['value' => '👍', 'label' => '👍 Thumbs Up'],
            ['value' => '✅', 'label' => '✅ Check'],
            ['value' => '☑️', 'label' => '☑️ Checkbox'],
            ['value' => '❌', 'label' => '❌ Cross'],
            ['value' => '⚠️', 'label' => '⚠️ Warning'],
            ['value' => 'ℹ️', 'label' => 'ℹ️ Info'],
            ['value' => '❓', 'label' => '❓ Question'],
            ['value' => '❗', 'label' => '❗ Important'],
            ['value' => '🔥', 'label' => '🔥 Fire'],
            ['value' => '🆕', 'label' => '🆕 New'],
            ['value' => '🆓', 'label' => '🆓 Free'],
            ['value' => '🔝', 'label' => '🔝 Top'],
            ['value' => '💯', 'label' => '💯 100'],
            ['value' => '🚀', 'label' => '🚀 Trending'],
            ['value' => '📈', 'label' => '📈 Rising'],
            ['value' => '📉', 'label' => '📉 Falling'],
            ['value' => '🎉', 'label' => '🎉 Celebration'],
            ['value' => '🎊', 'label' => '🎊 Party'],
            ['value' => '🎁', 'label' => '🎁 Offer'],
            ['value' => '🎈', 'label' => '🎈 Balloon'],
            ['value' => '🏆', 'label' => '🏆 Award'],
            ['value' => '🥇', 'label' => '🥇 Gold'],
            ['value' => '🥈', 'label' => '🥈 Silver'],
            ['value' => '🥉', 'label' => '🥉 Bronze'],
            ['value' => '💎', 'label' => '💎 Premium'],
            ['value' => '👑', 'label' => '👑 VIP'],
            ['value' => '⚡', 'label' => '⚡ Flash'],
            ['value' => '💥', 'label' => '💥 Boom'],
            ['value' => '✨', 'label' => '✨ Sparkles'],
            ['value' => '🌈', 'label' => '🌈 Rainbow'],
            ['value' => '💰', 'label' => '💰 Money'],
            ['value' => '💵', 'label' => '💵 Cash'],
            ['value' => '💳', 'label' => '💳 Card'],
            ['value' => '🧾', 'label' => '🧾 Receipt'],
            ['value' => '🛒', 'label' => '🛒 Cart'],
            ['value' => '🛍️', 'label' => '🛍️ Shopping'],
            ['value' => '🏬', 'label' => '🏬 Store'],
            ['value' => '📦', 'label' => '📦 Package'],
            ['value' => '🚚', 'label' => '🚚 Delivery'],
            ['value' => '✈️', 'label' => '✈️ Shipping'],
            ['value' => '🔄', 'label' => '🔄 Exchange'],
            ['value' => '↩️', 'label' => '↩️ Return'],
            ['value' => '🏦', 'label' => '🏦 Bank'],
            ['value' => '📊', 'label' => '📊 Analytics'],
            ['value' => '💸', 'label' => '💸 Discount'],
            ['value' => '🏥', 'label' => '🏥 Hospital'],
            ['value' => '🩺', 'label' => '🩺 Stethoscope'],
            ['value' => '💊', 'label' => '💊 Medicine'],
            ['value' => '💉', 'label' => '💉 Injection'],
            ['value' => '🩹', 'label' => '🩹 Bandage'],
            ['value' => '🧪', 'label' => '🧪 Lab'],
            ['value' => '🔬', 'label' => '🔬 Microscope'],
            ['value' => '🦷', 'label' => '🦷 Dental'],
            ['value' => '👁️', 'label' => '👁️ Eye'],
            ['value' => '🧬', 'label' => '🧬 Genetics'],
            ['value' => '🩻', 'label' => '🩻 X-Ray'],
            ['value' => '🧑‍⚕️', 'label' => '🧑‍⚕️ Doctor'],
            ['value' => '🚑', 'label' => '🚑 Ambulance'],
            ['value' => '♿', 'label' => '♿ Accessibility'],
            ['value' => '🧘', 'label' => '🧘 Wellness'],
            ['value' => '🏃', 'label' => '🏃 Fitness'],
            ['value' => '⏳', 'label' => '⏳ Soon'],
            ['value' => '⏰', 'label' => '⏰ Time'],
            ['value' => '📅', 'label' => '📅 Schedule'],
            ['value' => '🕐', 'label' => '🕐 Hours'],
            ['value' => '🔒', 'label' => '🔒 Locked'],
            ['value' => '🔓', 'label' => '🔓 Unlocked'],
            ['value' => '🔑', 'label' => '🔑 Key'],
            ['value' => '🛡️', 'label' => '🛡️ Protected'],
            ['value' => '♻️', 'label' => '♻️ Recycled'],
            ['value' => '🧹', 'label' => '🧹 Cleanup'],
            ['value' => '📢', 'label' => '📢 Announcement'],
            ['value' => '📣', 'label' => '📣 Megaphone'],
            ['value' => '🔔', 'label' => '🔔 Notification'],
            ['value' => '📞', 'label' => '📞 Phone'],
            ['value' => '📧', 'label' => '📧 Email'],
            ['value' => '💬', 'label' => '💬 Chat'],
            ['value' => '📝', 'label' => '📝 Note'],
            ['value' => '📄', 'label' => '📄 Document'],
            ['value' => '📁', 'label' => '📁 Folder'],
            ['value' => '🔍', 'label' => '🔍 Search'],
            ['value' => '🌍', 'label' => '🌍 Global'],
            ['value' => '🏠', 'label' => '🏠 Home'],
            ['value' => '🏢', 'label' => '🏢 Office'],
            ['value' => '🌱', 'label' => '🌱 Eco'],
            ['value' => '🍃', 'label' => '🍃 Natural'],
            ['value' => '☀️', 'label' => '☀️ Sun'],
            ['value' => '🌙', 'label' => '🌙 Night'],
            ['value' => '❄️', 'label' => '❄️ Cold'],
            ['value' => '💧', 'label' => '💧 Water'],
            ['value' => '🐾', 'label' => '🐾 Pet'],
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
