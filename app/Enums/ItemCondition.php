<?php

namespace App\Enums;

enum ItemCondition: string
{
    case Available = 'available';
    case InUse = 'in_use';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponível',
            self::InUse => 'Em uso',
            self::Returned => 'Devolvido',
        };
    }

    /**
     * @return array<int, ItemCondition>
     */
    public function nextAllowed(): array
    {
        return match ($this) {
            self::Available => [self::Available, self::InUse, self::Returned],
            self::InUse => [self::InUse, self::Returned],
            self::Returned => [self::Returned],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->nextAllowed(), strict: true);
    }
}
