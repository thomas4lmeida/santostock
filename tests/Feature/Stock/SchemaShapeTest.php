<?php

use App\Models\Attachment;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('orders has nullable warehouse_id FK', function () {
    expect(Schema::hasColumn('orders', 'warehouse_id'))->toBeTrue();
});

test('receipts has the augmented columns', function () {
    expect(Schema::hasColumns('receipts', [
        'order_id',
        'warehouse_id',
        'user_id',
        'quantity',
        'idempotency_key',
        'reason',
        'corrects_receipt_id',
    ]))->toBeTrue();
});

test('receipts.quantity is signed (allows negative for corrections)', function () {
    $order = Order::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $user = User::factory()->create();

    Receipt::factory()->create([
        'order_id' => $order->id,
        'warehouse_id' => $warehouse->id,
        'user_id' => $user->id,
        'quantity' => -5,
    ]);

    expect(Receipt::where('quantity', -5)->exists())->toBeTrue();
});

test('stock_lots table exists with expected columns', function () {
    expect(Schema::hasTable('stock_lots'))->toBeTrue();
    expect(Schema::hasColumns('stock_lots', [
        'product_id', 'warehouse_id', 'receipt_id',
    ]))->toBeTrue();
});

test('stock_movements table exists with signed quantity and audit columns', function () {
    expect(Schema::hasTable('stock_movements'))->toBeTrue();
    expect(Schema::hasColumns('stock_movements', [
        'stock_lot_id',
        'warehouse_id',
        'user_id',
        'type',
        'quantity',
        'idempotency_key',
        'corrects_movement_id',
    ]))->toBeTrue();
});

test('attachments table is non-polymorphic with concrete receipt_id', function () {
    expect(Schema::hasTable('attachments'))->toBeTrue();
    expect(Schema::hasColumn('attachments', 'receipt_id'))->toBeTrue();
    expect(Schema::hasColumn('attachments', 'attachable_type'))->toBeFalse();
    expect(Schema::hasColumn('attachments', 'attachable_id'))->toBeFalse();
    expect(Schema::hasColumns('attachments', [
        'receipt_id',
        'creator_id',
        'path',
        'thumbnail_path',
        'original_filename',
        'mime',
        'size',
        'sha256',
        'deleted_at',
    ]))->toBeTrue();
});

test('factories build coherent rows for the new models', function () {
    $lot = StockLot::factory()->create();
    $movement = StockMovement::factory()->for($lot, 'lot')->create();
    $attachment = Attachment::factory()->for($lot->receipt)->create();

    expect($lot->exists)->toBeTrue();
    expect($movement->lot->is($lot))->toBeTrue();
    expect($attachment->receipt->is($lot->receipt))->toBeTrue();
});
