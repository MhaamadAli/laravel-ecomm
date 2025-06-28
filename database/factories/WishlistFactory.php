<?php

/**
 * Wishlist Factory - Creates fake wishlist data for testing
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/factories/WishlistFactory.php
 */

namespace Database\Factories;

use App\Models\Wishlist;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wishlist>
 */
class WishlistFactory extends Factory
{
    protected $model = Wishlist::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
        ];
    }

    /**
     * Set the user for this wishlist item.
     */
    public function forUser($userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Set the product for this wishlist item.
     */
    public function forProduct($productId): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }
}