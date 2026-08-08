<?php

namespace App\Enums\Service;

enum ServiceTagEnum: string
{
    case NEW = 'new';
    case SOON = 'soon';
    case AVAILABLE = 'available';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::SOON => 'Coming Soon',
            self::AVAILABLE => 'Available',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
