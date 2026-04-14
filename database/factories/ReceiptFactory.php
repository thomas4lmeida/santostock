<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Receipt;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'warehouse_id' => Warehouse::factory(),
            'user_id' => User::factory(),
            'quantity' => fake()->numberBetween(1, 20),
            'idempotency_key' => Str::uuid()->toString(),
            'reason' => null,
            'corrects_receipt_id' => null,
        ];
    }

    public function correction(Receipt $original, ?int $delta = null): static
    {
        return $this->state(fn () => [
            'order_id' => $original->order_id,
            'warehouse_id' => $original->warehouse_id,
            'quantity' => $delta ?? -$original->quantity,
            'reason' => fake()->sentence(),
            'corrects_receipt_id' => $original->id,
        ]);
    }
}
