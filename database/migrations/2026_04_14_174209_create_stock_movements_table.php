<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_lot_id')->constrained('stock_lots')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->integer('quantity');
            $table->string('idempotency_key');
            $table->foreignId('corrects_movement_id')
                ->nullable()
                ->constrained('stock_movements')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['stock_lot_id', 'created_at']);
            $table->index('type');
            $table->index('warehouse_id');
            $table->index('corrects_movement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
