<?php

namespace Database\Factories;

use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_lot_id' => StockLot::factory(),
            'warehouse_id' => Warehouse::factory(),
            'user_id' => User::factory(),
            'type' => StockMovement::TYPE_RECEIPT,
            'quantity' => fake()->numberBetween(1, 20),
            'idempotency_key' => Str::uuid()->toString(),
            'corrects_movement_id' => null,
        ];
    }
}
