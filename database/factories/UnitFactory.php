<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $units = [
            'Unidade' => 'un',
            'Quilograma' => 'kg',
            'Metro' => 'm',
            'Par' => 'par',
            'Litro' => 'L',
        ];

        $name = $this->faker->unique()->randomElement(array_keys($units));

        return [
            'name' => $name,
            'abbreviation' => $units[$name],
        ];
    }
}
