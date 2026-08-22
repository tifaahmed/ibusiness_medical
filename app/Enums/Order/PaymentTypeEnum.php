<?php

namespace App\Enums\Order;

enum PaymentTypeEnum: string
{
    case COD = 'cod';
    case TRANSFER_WALLET = 'transfer-wallet';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        return [
            self::COD->value => ['value' => self::COD->value, 'label' => 'Cash on Delivery'],
            self::TRANSFER_WALLET->value => ['value' => self::TRANSFER_WALLET->value, 'label' => 'Transfer Wallet'],
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
