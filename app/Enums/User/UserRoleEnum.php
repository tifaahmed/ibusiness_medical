<?php

namespace App\Enums\User;

enum UserRoleEnum
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const MEMBER = 'member';

    public static function getRoles(): array
    {
        return [
            self::SUPER_ADMIN => [
                'id' => 1,
                'name' => self::SUPER_ADMIN,
                'label' => 'Super Administrator',
            ],
            self::ADMIN => [
                'id' => 2,
                'name' => self::ADMIN,
                'label' => 'Administrator',
            ],
            self::EDITOR => [
                'id' => 3,
                'name' => self::EDITOR,
                'label' => 'Editor',
            ],
            self::MEMBER => [
                'id' => 4,
                'name' => self::MEMBER,
                'label' => 'Member',
            ],
        ];
    }

    public static function getIdByName(string $name): ?int
    {
        return self::getRoles()[$name]['id'] ?? null;
    }

    public static function getLabelByName(string $name): ?string
    {
        return self::getRoles()[$name]['label'] ?? null;
    }

    public static function getOptions(): array
    {
        return array_values(array_map(function ($item) {
            return [
                'value' => $item['id'],
                'label' => $item['label']
            ];
        }, self::getRoles())) ?? [];
    }

    public static function protectedRoles(): array
    {
        return [self::SUPER_ADMIN, self::ADMIN];
    }

    public static function isProtected(string $name): bool
    {
        return in_array($name, self::protectedRoles(), true);
    }

    public static function descriptionFor(string $name): ?string
    {
        return match ($name) {
            self::SUPER_ADMIN => 'Full system access — manages users, roles, and all content.',
            self::ADMIN => 'Login only — grants admin dashboard access. Stack with another role to grant abilities.',
            self::EDITOR => 'Content editor — manages members, facilities, and facility branches.',
            self::MEMBER => 'Customer membership — not used for admin staff.',
            default => null,
        };
    }
}
