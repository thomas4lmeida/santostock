<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->restrictOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_filename');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->softDeletes();
            $table->timestamps();

            $table->index('receipt_id');
            $table->index('creator_id');
            $table->index(['receipt_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
