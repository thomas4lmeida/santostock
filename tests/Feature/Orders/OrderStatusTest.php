<?php

use App\Enums\OrderStatus;

test('Open transitions to PartiallyReceived and FullyReceived only among receiving states', function () {
    expect(OrderStatus::Open->canTransitionTo(OrderStatus::PartiallyReceived))->toBeTrue()
        ->and(OrderStatus::Open->canTransitionTo(OrderStatus::FullyReceived))->toBeTrue()
        ->and(OrderStatus::Open->canTransitionTo(OrderStatus::Cancelled))->toBeTrue()
        ->and(OrderStatus::Open->canTransitionTo(OrderStatus::ClosedShort))->toBeFalse()
        ->and(OrderStatus::Open->canTransitionTo(OrderStatus::Open))->toBeFalse();
});

test('PartiallyReceived transitions to FullyReceived, Cancelled, or ClosedShort', function () {
    expect(OrderStatus::PartiallyReceived->canTransitionTo(OrderStatus::FullyReceived))->toBeTrue()
        ->and(OrderStatus::PartiallyReceived->canTransitionTo(OrderStatus::Cancelled))->toBeTrue()
        ->and(OrderStatus::PartiallyReceived->canTransitionTo(OrderStatus::ClosedShort))->toBeTrue()
        ->and(OrderStatus::PartiallyReceived->canTransitionTo(OrderStatus::Open))->toBeFalse();
});

test('terminal states allow no further transitions', function () {
    foreach ([OrderStatus::FullyReceived, OrderStatus::Cancelled, OrderStatus::ClosedShort] as $terminal) {
        expect($terminal->nextAllowed())->toBe([]);
        foreach (OrderStatus::cases() as $next) {
            expect($terminal->canTransitionTo($next))->toBeFalse();
        }
    }
});

test('labels are returned in pt-BR', function () {
    expect(OrderStatus::Open->label())->toBe('Aberto')
        ->and(OrderStatus::PartiallyReceived->label())->toBe('Recebido parcialmente')
        ->and(OrderStatus::FullyReceived->label())->toBe('Recebido integralmente')
        ->and(OrderStatus::Cancelled->label())->toBe('Cancelado')
        ->and(OrderStatus::ClosedShort->label())->toBe('Encerrado com saldo curto');
});
