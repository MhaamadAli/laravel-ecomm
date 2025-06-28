<?php

/**
 * Products Table Migration - Creates products table with inventory and pricing
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/migrations/2024_01_01_000002_create_products_table.php
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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('sku', 50)->unique()->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('featured')->default(false);
            $table->timestamps();

            // Indexes for performance
            $table->index(['is_active']);
            $table->index(['featured']);
            $table->index(['category_id', 'is_active']);
            $table->index(['is_active', 'featured']);
            $table->index(['price', 'is_active']);
            $table->index(['stock_quantity']);
            $table->index(['name', 'is_active']);
            $table->index(['created_at']);
            
            // Fulltext index for search
            $table->fullText(['name', 'description', 'short_description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};