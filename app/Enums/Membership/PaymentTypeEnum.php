<?php

namespace App\Enums\Membership;

enum PaymentTypeEnum: string
{
    case YEARLY = 'yearly';
    case MONTHLY = 'monthly';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        return [
            self::YEARLY->value => [
                'value' => self::YEARLY->value,
                'label' => __('admin.member.payment_type_yearly'),
            ],
            self::MONTHLY->value => [
                'value' => self::MONTHLY->value,
                'label' => __('admin.member.payment_type_monthly'),
            ],
        ];
    }

    public static function getLabel(string $value): ?string
    {
        return self::getOptions()[$value]['label'] ?? null;
    }

    public function label(): string
    {
        return self::getLabel($this->value) ?? $this->value;
    }
}
