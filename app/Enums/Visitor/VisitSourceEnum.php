<?php

namespace App\Enums\Visitor;

/** Where a visit came from: the website's guest pages, or one of the two apps. */
enum VisitSourceEnum: string
{
    case WEB = 'web';
    case ANDROID = 'android';
    case IOS = 'ios';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, array{value: string, label: string}> */
    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => ['value' => $case->value, 'label' => $case->label()]])
            ->all();
    }

    public static function getLabel(string $value): ?string
    {
        return self::tryFrom($value)?->label();
    }

    public function label(): string
    {
        return __('admin.visitors.source_'.$this->value);
    }
}
