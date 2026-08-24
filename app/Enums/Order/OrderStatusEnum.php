<?php

namespace App\Enums\Order;

/**
 * How the order ended up, as the shop reports it.
 *
 * Deliberately separate from `payment_status` and `delivery_status`: those two
 * say where the money and the parcel are, and an order can be sitting at
 * "accepted / on-delivery" while nobody has yet said whether the order itself
 * came off. This is the one column a report counts by, and the one the list
 * page lets an admin move for a whole page of orders at once.
 */
enum OrderStatusEnum: string
{
    case PENDING = 'pending';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        return [
            self::PENDING->value => ['value' => self::PENDING->value, 'label' => 'Pending'],
            self::SUCCESS->value => ['value' => self::SUCCESS->value, 'label' => 'Success'],
            self::FAILED->value => ['value' => self::FAILED->value, 'label' => 'Failed'],
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
