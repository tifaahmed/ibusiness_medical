<?php

namespace App\Enums\Address;

enum AddressTypeEnum: string
{
    case HOME = 'home';
    case WORK = 'work';
    case OTHER = 'other';

    /**
     * Get all address type values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all address types with labels
     *
     * @return array<string, array{value: string, label: string}>
     */
    public static function getTypes(): array
    {
        return [
            self::HOME->value => [
                'value' => self::HOME->value,
                'label' => __('admin.member.address_type_home'),
            ],
            self::WORK->value => [
                'value' => self::WORK->value,
                'label' => __('admin.member.address_type_work'),
            ],
            self::OTHER->value => [
                'value' => self::OTHER->value,
                'label' => __('admin.member.address_type_other'),
            ],
        ];
    }

    /**
     * Get label for an address type value
     *
     * @param string $value
     * @return string|null
     */
    public static function getLabel(string $value): ?string
    {
        return self::getTypes()[$value]['label'] ?? null;
    }

    /**
     * Get options for select dropdowns
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getOptions(): array
    {
        return array_values(self::getTypes());
    }

    /**
     * Get the label for this address type
     *
     * @return string
     */
    public function label(): string
    {
        return self::getLabel($this->value) ?? $this->value;
    }
}
