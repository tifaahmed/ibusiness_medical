<?php

namespace App\Enums\Order;

enum DeliveryStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case ON_DELIVERY = 'on-delivery';
    case COMPLETED = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        return [
            self::PENDING->value => ['value' => self::PENDING->value, 'label' => 'Pending'],
            self::PROCESSING->value => ['value' => self::PROCESSING->value, 'label' => 'Processing'],
            self::ON_DELIVERY->value => ['value' => self::ON_DELIVERY->value, 'label' => 'On Delivery'],
            self::COMPLETED->value => ['value' => self::COMPLETED->value, 'label' => 'Completed'],
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
