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

    /** A filter value meaning "either app": Android and iOS together, to set the apps against the website. */
    public const MOBILE = 'mobile';

    /**
     * The sources a filter value stands for: one source, both apps for
     * `mobile`, or null (no filter) for anything else, `all` included.
     *
     * @return array<int, string>|null
     */
    public static function forFilter(?string $filter): ?array
    {
        return match (true) {
            $filter === self::MOBILE => [self::ANDROID->value, self::IOS->value],
            in_array($filter, self::values(), true) => [$filter],
            default => null,
        };
    }
}
