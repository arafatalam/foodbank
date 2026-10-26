<?php

namespace App\Enums;

enum RoleCode: string
{
    case SUPER_ADMIN = 'super_admin';
    case MANAGER = 'manager';
    case VOLUNTEER = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::MANAGER => 'Manager',
            self::VOLUNTEER => 'Volunteer',
        };
    }
}
