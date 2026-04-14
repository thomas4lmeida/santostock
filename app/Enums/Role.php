<?php

namespace App\Enums;

enum Role: string
{
    case Administrador = 'administrador';
    case Operador = 'operador';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Operador => 'Operador',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
