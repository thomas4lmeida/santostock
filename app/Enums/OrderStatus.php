<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Open = 'open';
    case PartiallyReceived = 'partially_received';
    case FullyReceived = 'fully_received';
    case Cancelled = 'cancelled';
    case ClosedShort = 'closed_short';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::PartiallyReceived => 'Recebido parcialmente',
            self::FullyReceived => 'Recebido integralmente',
            self::Cancelled => 'Cancelado',
            self::ClosedShort => 'Encerrado com saldo curto',
        };
    }

    /**
     * @return array<int, OrderStatus>
     */
    public function nextAllowed(): array
    {
        return match ($this) {
            self::Open => [self::PartiallyReceived, self::FullyReceived, self::Cancelled],
            self::PartiallyReceived => [self::FullyReceived, self::Cancelled, self::ClosedShort],
            self::FullyReceived, self::Cancelled, self::ClosedShort => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->nextAllowed(), strict: true);
    }

    /**
     * @return array<int, OrderStatus>
     */
    public function rewindTargets(): array
    {
        return match ($this) {
            self::FullyReceived => [self::PartiallyReceived, self::Open],
            self::PartiallyReceived => [self::Open],
            self::ClosedShort => [self::PartiallyReceived, self::Open],
            self::Open, self::Cancelled => [],
        };
    }

    public function canRewindTo(self $next): bool
    {
        return in_array($next, $this->rewindTargets(), strict: true);
    }
}
