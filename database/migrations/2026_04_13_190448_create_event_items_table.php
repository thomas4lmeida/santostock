<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_category_id')->constrained('item_categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('rental_cost_cents')->default(0);
            $table->string('condition')->default('available');
            $table->timestamps();

            $table->index(['event_id', 'item_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_items');
    }
};
