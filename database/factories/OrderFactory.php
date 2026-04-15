<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'product_id' => Product::factory(),
            'ordered_quantity' => fake()->numberBetween(5, 100),
            'status' => OrderStatus::Open,
            'notes' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function open(): self
    {
        return $this->state(['status' => OrderStatus::Open]);
    }

    public function partiallyReceived(): self
    {
        return $this->state(['status' => OrderStatus::PartiallyReceived]);
    }

    public function fullyReceived(): self
    {
        return $this->state(['status' => OrderStatus::FullyReceived]);
    }

    public function cancelled(): self
    {
        return $this->state(['status' => OrderStatus::Cancelled]);
    }

    public function closedShort(): self
    {
        return $this->state(['status' => OrderStatus::ClosedShort]);
    }
}
