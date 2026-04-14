<?php

namespace App\Models;

use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_RECEIPT_CORRECTION = 'receipt_correction';

    protected $fillable = [
        'stock_lot_id',
        'warehouse_id',
        'user_id',
        'type',
        'quantity',
        'idempotency_key',
        'corrects_movement_id',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_movement_id');
    }
}
