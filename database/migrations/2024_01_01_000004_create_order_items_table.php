<?php

/**
 * Order Items Table Migration - Creates order_items table for individual order products
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/migrations/2024_01_01_000004_create_order_items_table.php
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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();

            // Indexes for performance
            $table->index(['order_id']);
            $table->index(['product_id']);
            $table->index(['order_id', 'product_id']);
            $table->index(['price']);
            $table->index(['total']);
            
            // Unique constraint to prevent duplicate order items
            $table->unique(['order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};