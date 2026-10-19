<?php

namespace App\Enums\Order;

enum PaymentTypeEnum: string
{
    case COD = 'cod';
    case TRANSFER_WALLET = 'transfer-wallet';
    case PAYMOB = 'paymob';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The types that need no gateway. Memberships reuse this enum for their own
     * payment column, which has no online option — they validate against this.
     *
     * @return list<string>
     */
    public static function offlineValues(): array
    {
        return array_values(array_diff(self::values(), [self::PAYMOB->value]));
    }

    public static function getOptions(): array
    {
        return [
            self::COD->value => ['value' => self::COD->value, 'label' => 'Cash on Delivery'],
            self::TRANSFER_WALLET->value => ['value' => self::TRANSFER_WALLET->value, 'label' => 'Transfer Wallet'],
            self::PAYMOB->value => ['value' => self::PAYMOB->value, 'label' => 'Online (Paymob)'],
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
