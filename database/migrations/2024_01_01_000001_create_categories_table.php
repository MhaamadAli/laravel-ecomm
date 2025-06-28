<?php

/**
 * Categories Table Migration - Creates categories table with hierarchical structure
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/migrations/2024_01_01_000001_create_categories_table.php
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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['is_active']);
            $table->index(['parent_id']);
            $table->index(['is_active', 'parent_id']);
            $table->index(['name', 'is_active']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};