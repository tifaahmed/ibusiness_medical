<?php

namespace App\Enums\FamilyMember;

enum RelationshipEnum: string
{
    case WIFE = 'wife';
    case HUSBAND = 'husband';
    case SON = 'son';
    case DAUGHTER = 'daughter';
    case FATHER = 'father';
    case MOTHER = 'mother';
    case BROTHER = 'brother';
    case SISTER = 'sister';

    /**
     * Get all relationship values as array
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all relationships with labels
     *
     * @return array<string, array{value: string, label: string}>
     */
    public static function getRelationships(): array
    {
        return [
            self::WIFE->value => [
                'value' => self::WIFE->value,
                'label' => __('admin.member.wife'),
            ],
            self::HUSBAND->value => [
                'value' => self::HUSBAND->value,
                'label' => __('admin.member.husband'),
            ],
            self::SON->value => [
                'value' => self::SON->value,
                'label' => __('admin.member.son'),
            ],
            self::DAUGHTER->value => [
                'value' => self::DAUGHTER->value,
                'label' => __('admin.member.daughter'),
            ],
            self::FATHER->value => [
                'value' => self::FATHER->value,
                'label' => __('admin.member.father'),
            ],
            self::MOTHER->value => [
                'value' => self::MOTHER->value,
                'label' => __('admin.member.mother'),
            ],
            self::BROTHER->value => [
                'value' => self::BROTHER->value,
                'label' => __('admin.member.brother'),
            ],
            self::SISTER->value => [
                'value' => self::SISTER->value,
                'label' => __('admin.member.sister'),
            ],
        ];
    }

    /**
     * Get label for a relationship value
     *
     * @param string $value
     * @return string|null
     */
    public static function getLabel(string $value): ?string
    {
        return self::getRelationships()[$value]['label'] ?? null;
    }

    /**
     * Get options for select dropdowns
     *
     * @return array<array{value: string, label: string}>
     */
    public static function getOptions(): array
    {
        return array_values(self::getRelationships());
    }

    /**
     * Get the label for this relationship
     *
     * @return string
     */
    public function label(): string
    {
        return self::getLabel($this->value) ?? $this->value;
    }
}

