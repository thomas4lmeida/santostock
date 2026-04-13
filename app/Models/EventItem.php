<?php

namespace App\Models;

use App\Enums\ItemCondition;
use Database\Factories\EventItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'item_category_id', 'supplier_id', 'name', 'quantity', 'rental_cost_cents', 'condition'])]
class EventItem extends Model
{
    /** @use HasFactory<EventItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'condition' => ItemCondition::class,
            'quantity' => 'integer',
            'rental_cost_cents' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
