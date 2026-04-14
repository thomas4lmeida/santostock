<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('spaces');
    Queue::fake();
});

function makeReceipt(int $quantity = 10, ?User $by = null): Receipt
{
    $by ??= tap(User::factory()->create(), fn ($u) => $u->givePermissionTo('receipts.create'));
    $order = Order::factory()->create(['ordered_quantity' => $quantity, 'status' => OrderStatus::Open]);
    $warehouse = Warehouse::factory()->create();

    test()->actingAs($by)->post("/pedidos/{$order->id}/recebimentos", [
        'warehouse_id' => $warehouse->id,
        'quantity' => $quantity,
        'idempotency_key' => Str::uuid()->toString(),
        'photos' => [UploadedFile::fake()->image('p.jpg')],
    ])->assertRedirect();

    return Receipt::firstOrFail();
}

test('admin with receipts.correct can correct a fully received receipt and rewind status', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('receipts.correct');

    $receipt = makeReceipt(10);

    expect($receipt->order->fresh()->status)->toBe(OrderStatus::FullyReceived);

    $this->actingAs($admin)
        ->post("/recebimentos/{$receipt->id}/corrigir", [
            'delta_quantity' => -3,
            'reason' => 'Operador contou errado',
        ])
        ->assertRedirect();

    expect($receipt->order->fresh()->status)->toBe(OrderStatus::PartiallyReceived)
        ->and(Receipt::count())->toBe(2);

    $correction = Receipt::where('corrects_receipt_id', $receipt->id)->firstOrFail();
    expect($correction->quantity)->toBe(-3)
        ->and($correction->reason)->toBe('Operador contou errado');

    $correctionMovement = StockMovement::where('type', StockMovement::TYPE_RECEIPT_CORRECTION)->firstOrFail();
    expect($correctionMovement->quantity)->toBe(-3)
        ->and($correctionMovement->corrects_movement_id)->toBe(StockMovement::where('type', StockMovement::TYPE_RECEIPT)->first()->id);
});

test('user without receipts.correct gets 403', function () {
    $user = User::factory()->create();
    $receipt = makeReceipt(10);

    $this->actingAs($user)
        ->post("/recebimentos/{$receipt->id}/corrigir", [
            'delta_quantity' => -3,
            'reason' => 'sem permissão',
        ])
        ->assertForbidden();
});

test('correction without reason is rejected', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('receipts.correct');
    $receipt = makeReceipt(10);

    $this->actingAs($admin)
        ->from('/')
        ->post("/recebimentos/{$receipt->id}/corrigir", [
            'delta_quantity' => -3,
        ])
        ->assertSessionHasErrors('reason');
});

test('over-correction is rejected', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('receipts.correct');
    $receipt = makeReceipt(10);

    $this->actingAs($admin)
        ->from('/')
        ->post("/recebimentos/{$receipt->id}/corrigir", [
            'delta_quantity' => -20,
            'reason' => 'erro',
        ])
        ->assertSessionHasErrors('delta_quantity');
});

test('correction on a Cancelled order is rejected', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('receipts.correct');
    $receipt = makeReceipt(10);
    $receipt->order->update(['status' => OrderStatus::Cancelled]);

    $this->actingAs($admin)
        ->from('/')
        ->post("/recebimentos/{$receipt->id}/corrigir", [
            'delta_quantity' => -3,
            'reason' => 'erro',
        ])
        ->assertSessionHasErrors();

    expect(Receipt::count())->toBe(1);
});
