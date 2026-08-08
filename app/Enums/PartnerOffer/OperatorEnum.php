<?php

namespace App\Enums\PartnerOffer;

enum OperatorEnum: string
{
    case VODAFONE = 'vodafone';
    case ETISALAT = 'etisalat';
    case ORANGE = 'orange';
    case WE = 'we';

    public function title(): string
    {
        return match ($this) {
            self::VODAFONE => 'Vodafone',
            self::ETISALAT => 'Etisalat',
            self::ORANGE => 'Orange',
            self::WE => 'We',
        };
    }

    public function name(): string
    {
        return match ($this) {
            self::VODAFONE => 'Vodafone Egypt',
            self::ETISALAT => 'Etisalat Egypt',
            self::ORANGE => 'Orange Egypt',
            self::WE => 'We Egypt',
        };
    }

    public function logo(): string
    {
        return match ($this) {
            self::VODAFONE => '/images/phone-companies/vodafone.jpg',
            self::ETISALAT => '/images/phone-companies/etisalat.png',
            self::ORANGE => '/images/phone-companies/orange.png',
            self::WE => '/images/phone-companies/we.jpg',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = [
                'value' => $case->value,
                'title' => $case->title(),
                'name' => $case->name(),
                'logo' => $case->logo(),
            ];
        }
        return $options;
    }
}
