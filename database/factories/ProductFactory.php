<?php

namespace Database\Factories;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'item_category_id' => ItemCategory::factory(),
            'unit_id' => Unit::factory(),
        ];
    }
}
