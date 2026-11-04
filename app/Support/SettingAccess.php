<?php

namespace App\Support;

use App\Enums\User\UserPermissionEnum;
use App\Models\Setting;
use App\Models\User;

/**
 * Who may touch which row of `settings`.
 *
 * Ordinary rows need `manage settings`. The quick-add message templates (QuickAdd::templateSlugs()) need their own
 * permission instead, so the text customers receive can be handed to someone who cannot change prices or Paymob keys,
 * and a `manage settings` holder cannot rewrite the messages without it. Always ask the Gate (`can`), never the tables,
 * so the super admin passes.
 */
class SettingAccess
{
    /** The permission that guards this row, or null for an ordinary setting. SMS texts and WhatsApp texts have one each. */
    public static function templatePermission(Setting $setting): ?string
    {
        return match (true) {
            in_array($setting->slug, QuickAdd::templateSlugs(), true) => UserPermissionEnum::EDIT_QUICK_ADD_TEMPLATES,
            in_array($setting->slug, QuickAdd::whatsappSlugs(), true) => UserPermissionEnum::EDIT_WHATSAPP_TEMPLATES,
            default => null,
        };
    }

    public static function isTemplate(Setting $setting): bool
    {
        return self::templatePermission($setting) !== null;
    }

    /** Every message-text slug (SMS + WhatsApp). */
    public static function templateSlugsAll(): array
    {
        return array_merge(QuickAdd::templateSlugs(), QuickAdd::whatsappSlugs());
    }

    public static function canEdit(?User $user, Setting $setting): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can(self::templatePermission($setting) ?? UserPermissionEnum::MANAGE_SETTINGS);
    }

    /** The message-text slugs this admin may edit (SMS texts need their permission, WhatsApp texts theirs). */
    public static function editableTemplateSlugs(?User $user): array
    {
        return array_merge(
            $user?->can(UserPermissionEnum::EDIT_QUICK_ADD_TEMPLATES) ? QuickAdd::templateSlugs() : [],
            $user?->can(UserPermissionEnum::EDIT_WHATSAPP_TEMPLATES) ? QuickAdd::whatsappSlugs() : [],
        );
    }

    /** True when this admin can read ordinary rows (not only the templates). */
    public static function seesAllRows(?User $user): bool
    {
        return (bool) $user?->canAny([UserPermissionEnum::MANAGE_SETTINGS, UserPermissionEnum::VIEW_SETTINGS]);
    }
}
