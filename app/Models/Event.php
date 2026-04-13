<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'venue', 'starts_at', 'ends_at'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime:Y-m-d\TH:i:s',
            'ends_at' => 'datetime:Y-m-d\TH:i:s',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventItem::class);
    }
}
