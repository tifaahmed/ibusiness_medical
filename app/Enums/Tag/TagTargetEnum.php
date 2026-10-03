<?php

namespace App\Enums\Tag;

/**
 * What a tag may be attached to. A tag can apply to several at once.
 */
enum TagTargetEnum: string
{
    case FACILITIES = 'facilities';
    case PRODUCTS = 'products';
    case STORES = 'stores';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getLabel(string $value): ?string
    {
        return self::tryFrom($value)?->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::FACILITIES => 'Facilities',
            self::PRODUCTS => 'Products',
            self::STORES => 'Stores',
        };
    }

    public static function getOptions(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
