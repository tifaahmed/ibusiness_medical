<?php

namespace App\Enums\FamilyMember;

enum ApprovalStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<array{value: string, label: string}> */
    public static function getOptions(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }

    public static function getLabel(string $value): ?string
    {
        return self::tryFrom($value)?->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }
}
