<?php

namespace App\Enums\CardTemplate;

enum CardTemplateStatusEnum: string
{
    case WITH_PARTNER = 'with_partner';
    case NO_PARTNER = 'no_partner';

    /**
     * layout/sample_data field keys the admin form should hide for this status,
     * so a card with no partner never shows a partner-logo slot to position.
     *
     * @return array<string>
     */
    public function hiddenFields(): array
    {
        return match ($this) {
            self::WITH_PARTNER => [],
            self::NO_PARTNER => ['partner_logo'],
        };
    }

    /**
     * Get all status values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::WITH_PARTNER => 'With partner',
            self::NO_PARTNER => 'No partners',
        };
    }

    /**
     * Get all statuses with labels, for select dropdowns.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
