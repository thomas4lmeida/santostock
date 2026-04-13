<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'venue', 'starts_at', 'ends_at'])]
class Event extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime:Y-m-d\TH:i:s',
            'ends_at' => 'datetime:Y-m-d\TH:i:s',
        ];
    }
}
