<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'warehouse_id',
        'user_id',
        'quantity',
        'idempotency_key',
        'reason',
        'corrects_receipt_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
        return $this->belongsTo(self::class, 'corrects_receipt_id');
    }

    public function correctedBy(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_receipt_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function lot(): HasOne
    {
        return $this->hasOne(StockLot::class);
    }
}
