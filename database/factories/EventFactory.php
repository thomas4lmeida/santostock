<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+2 months');
        $end = (clone $start)->modify('+6 hours');

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'venue' => fake()->company(),
            'starts_at' => $start,
            'ends_at' => $end,
        ];
    }
}
