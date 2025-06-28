<?php

/**
 * Orders Table Migration - Creates orders table for customer purchases
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/migrations/2024_01_01_000003_create_orders_table.php
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('order_number', 50)->unique();
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->decimal('total_amount', 10, 2);
            $table->json('shipping_address');
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id']);
            $table->index(['status']);
            $table->index(['created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['total_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};