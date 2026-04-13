<?php

namespace Database\Factories;

use App\Enums\ItemCondition;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventItem>
 */
class EventItemFactory extends Factory
{
    protected $model = EventItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'item_category_id' => ItemCategory::factory(),
            'supplier_id' => null,
            'name' => fake()->words(2, true),
            'quantity' => fake()->numberBetween(1, 50),
            'rental_cost_cents' => fake()->numberBetween(0, 100000),
            'condition' => ItemCondition::Available->value,
        ];
    }
}
