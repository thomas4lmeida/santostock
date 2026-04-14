<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->integer('quantity')->change();

            $table->foreignId('warehouse_id')
                ->after('order_id')
                ->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->after('quantity')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('idempotency_key')->after('user_id');
            $table->text('reason')->nullable()->after('idempotency_key');
            $table->foreignId('corrects_receipt_id')
                ->nullable()
                ->after('reason')
                ->constrained('receipts')
                ->nullOnDelete();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index('corrects_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropConstrainedForeignId('corrects_receipt_id');
            $table->dropColumn('reason');
            $table->dropColumn('idempotency_key');
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->unsignedInteger('quantity')->change();
        });
    }
};
