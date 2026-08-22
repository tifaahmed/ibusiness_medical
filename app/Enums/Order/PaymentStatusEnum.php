<?php

namespace App\Enums\Order;

enum PaymentStatusEnum: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELED = 'canceled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        return [
            self::PENDING->value => ['value' => self::PENDING->value, 'label' => 'Pending'],
            self::ACCEPTED->value => ['value' => self::ACCEPTED->value, 'label' => 'Accepted'],
            self::REJECTED->value => ['value' => self::REJECTED->value, 'label' => 'Rejected'],
            self::CANCELED->value => ['value' => self::CANCELED->value, 'label' => 'Canceled'],
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
