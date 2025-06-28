<?php

/**
 * Reviews Table Migration - Creates reviews table for product reviews and ratings
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/migrations/2024_01_01_000005_create_reviews_table.php
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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned()->check('rating >= 1 AND rating <= 5');
            $table->string('title', 255)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            // Unique constraint to prevent multiple reviews per user per product
            $table->unique(['user_id', 'product_id']);
            
            // Indexes for performance
            $table->index(['product_id']);
            $table->index(['user_id']);
            $table->index(['rating']);
            $table->index(['is_approved']);
            $table->index(['product_id', 'is_approved']);
            $table->index(['product_id', 'rating']);
            $table->index(['user_id', 'created_at']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};