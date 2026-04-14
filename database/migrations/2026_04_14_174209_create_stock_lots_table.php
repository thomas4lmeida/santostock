<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('receipt_id')->constrained('receipts')->restrictOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id']);
            $table->index('receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
