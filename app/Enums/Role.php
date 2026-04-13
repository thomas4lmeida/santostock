<?php

namespace App\Enums;

enum Role: string
{
    case Coordinator = 'coordinator';
    case Staff = 'staff';
    case Client = 'client';

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
