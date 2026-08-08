<?php

namespace App\Enums\ServiceType;

enum ServiceTypeEnum: string
{
    case HOSPITAL = 'hospital';
    case CLINIC = 'clinic';
    case DOCTOR = 'doctor';
    case PHARMACY = 'pharmacy';
    case DENTAL = 'dental';
    case LAB = 'lab';
    case OTHER = 'other';

    /**
     * Get all service type values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all service types with labels
     *
     * @return array<string, array{value: string, label: string}>
     */
    public static function getServiceTypes(): array
    {
        return [
            self::HOSPITAL->value => [
                'value' => self::HOSPITAL->value,
                'label' => __('admin.serviceTypes.hospital'),
            ],
            self::CLINIC->value => [
                'value' => self::CLINIC->value,
                'label' => __('admin.serviceTypes.clinic'),
            ],
            self::DOCTOR->value => [
                'value' => self::DOCTOR->value,
                'label' => __('admin.serviceTypes.doctor'),
            ],
            self::PHARMACY->value => [
                'value' => self::PHARMACY->value,
                'label' => __('admin.serviceTypes.pharmacy'),
            ],
            self::DENTAL->value => [
                'value' => self::DENTAL->value,
                'label' => __('admin.serviceTypes.dental'),
            ],
            self::LAB->value => [
                'value' => self::LAB->value,
                'label' => __('admin.serviceTypes.lab'),
            ],
            self::OTHER->value => [
                'value' => self::OTHER->value,
                'label' => __('admin.serviceTypes.other'),
            ],
        ];
    }

    /**
     * Get label for a service type value
     *
     * @param string $value
     * @return string|null
     */
    public static function getLabel(string $value): ?string
    {
        return self::getServiceTypes()[$value]['label'] ?? null;
    }

    /**
     * Get options for select dropdowns
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getOptions(): array
    {
        return array_values(self::getServiceTypes());
    }

    /**
     * Get the label for this service type
     *
     * @return string
     */
    public function label(): string
    {
        return self::getLabel($this->value) ?? $this->value;
    }

    /**
     * Get icon options for select dropdowns
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getIconOptions(): array
    {
        return [
            ['value' => '🏥', 'label' => '🏥 ' . __('admin.serviceTypes.hospital')],
            ['value' => '🏨', 'label' => '🏨 ' . __('admin.serviceTypes.clinic')],
            ['value' => '👨‍⚕️', 'label' => '👨‍⚕️ ' . __('admin.serviceTypes.doctor')],
            ['value' => '💊', 'label' => '💊 ' . __('admin.serviceTypes.pharmacy')],
            ['value' => '🦷', 'label' => '🦷 ' . __('admin.serviceTypes.dental')],
            ['value' => '🔬', 'label' => '🔬 ' . __('admin.serviceTypes.lab')],
            ['value' => '📋', 'label' => '📋 ' . __('admin.serviceTypes.other')],
        ];
    }

    /**
     * Get color options for select dropdowns
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
            ['value' => '#6B7280', 'label' => 'Gray'],
        ];
    }
}
