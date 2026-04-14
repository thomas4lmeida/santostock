<?php

use App\Enums\OrderStatus;
use App\Jobs\ProcessAttachmentJob;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\StockLot;
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

test('user with receipts.create can post a receipt and order auto-transitions to fully received', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('receipts.create');

    $order = Order::factory()->create(['ordered_quantity' => 10, 'status' => OrderStatus::Open]);
    $warehouse = Warehouse::factory()->create();

    $response = $this->actingAs($user)->post("/pedidos/{$order->id}/recebimentos", [
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'idempotency_key' => Str::uuid()->toString(),
        'photos' => [UploadedFile::fake()->image('p.jpg')],
    ]);

    $response->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::FullyReceived)
        ->and($order->fresh()->warehouse_id)->toBe($warehouse->id)
        ->and(Receipt::count())->toBe(1)
        ->and(StockLot::count())->toBe(1)
        ->and(StockMovement::count())->toBe(1)
        ->and(StockMovement::first()->quantity)->toBe(10)
        ->and(StockMovement::first()->type)->toBe(StockMovement::TYPE_RECEIPT)
        ->and(Attachment::count())->toBe(1);

    Queue::assertPushed(ProcessAttachmentJob::class, 1);
});

test('partial receipt transitions order to PartiallyReceived', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('receipts.create');

    $order = Order::factory()->create(['ordered_quantity' => 10, 'status' => OrderStatus::Open]);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($user)->post("/pedidos/{$order->id}/recebimentos", [
        'warehouse_id' => $warehouse->id,
        'quantity' => 4,
        'idempotency_key' => Str::uuid()->toString(),
        'photos' => [UploadedFile::fake()->image('p.jpg')],
    ])->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::PartiallyReceived);
});

test('quantity over saldo is rejected with validation error', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('receipts.create');

    $order = Order::factory()->create(['ordered_quantity' => 5, 'status' => OrderStatus::Open]);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($user)
        ->from("/pedidos/{$order->id}")
        ->post("/pedidos/{$order->id}/recebimentos", [
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'idempotency_key' => Str::uuid()->toString(),
            'photos' => [UploadedFile::fake()->image('p.jpg')],
        ])
        ->assertSessionHasErrors('quantity');

    expect(Receipt::count())->toBe(0);
});

test('repeating idempotency_key does not duplicate', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('receipts.create');

    $order = Order::factory()->create(['ordered_quantity' => 10, 'status' => OrderStatus::Open]);
    $warehouse = Warehouse::factory()->create();
    $key = Str::uuid()->toString();

    $payload = [
        'warehouse_id' => $warehouse->id,
        'quantity' => 4,
        'idempotency_key' => $key,
        'photos' => [UploadedFile::fake()->image('p.jpg')],
    ];

    $this->actingAs($user)->post("/pedidos/{$order->id}/recebimentos", $payload)->assertRedirect();
    $this->actingAs($user)->post("/pedidos/{$order->id}/recebimentos", $payload)->assertRedirect();

    expect(Receipt::count())->toBe(1)
        ->and(StockMovement::count())->toBe(1);
});

test('warehouse lock — second receipt with different warehouse is rejected', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('receipts.create');

    $order = Order::factory()->create(['ordered_quantity' => 10, 'status' => OrderStatus::Open]);
    [$first, $second] = Warehouse::factory()->count(2)->create();

    $this->actingAs($user)->post("/pedidos/{$order->id}/recebimentos", [
        'warehouse_id' => $first->id,
        'quantity' => 4,
        'idempotency_key' => Str::uuid()->toString(),
        'photos' => [UploadedFile::fake()->image('p.jpg')],
    ])->assertRedirect();

    $this->actingAs($user)
        ->from("/pedidos/{$order->id}")
        ->post("/pedidos/{$order->id}/recebimentos", [
            'warehouse_id' => $second->id,
            'quantity' => 4,
            'idempotency_key' => Str::uuid()->toString(),
            'photos' => [UploadedFile::fake()->image('p.jpg')],
        ])
        ->assertSessionHasErrors('warehouse_id');

    expect(Receipt::count())->toBe(1);
});

test('user without receipts.create gets 403', function () {
    $user = User::factory()->create();

    $order = Order::factory()->create(['ordered_quantity' => 10, 'status' => OrderStatus::Open]);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($user)
        ->post("/pedidos/{$order->id}/recebimentos", [
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'idempotency_key' => Str::uuid()->toString(),
            'photos' => [UploadedFile::fake()->image('p.jpg')],
        ])
        ->assertForbidden();
});
