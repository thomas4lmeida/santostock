<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Receipt;
use App\Models\StockLot;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLot>
 */
class StockLotFactory extends Factory
{
    protected $model = StockLot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'receipt_id' => Receipt::factory(),
        ];
    }
}
