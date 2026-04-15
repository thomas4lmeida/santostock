<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $casts = [
        'status' => OrderStatus::class,
        'supplier_id' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'ordered_quantity' => 'integer',
        'created_by_user_id' => 'integer',
    ];

    protected $fillable = [
        'supplier_id',
        'product_id',
        'warehouse_id',
        'ordered_quantity',
        'status',
        'notes',
        'created_by_user_id',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
