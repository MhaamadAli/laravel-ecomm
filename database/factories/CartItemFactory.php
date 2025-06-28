<?php

/**
 * Cart Item Factory - Creates fake cart item data for testing
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/factories/CartItemFactory.php
 */

namespace Database\Factories;

use App\Models\CartItem;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

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
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }

    /**
     * Set the user for this cart item.
     */
    public function forUser($userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Set the product for this cart item.
     */
    public function forProduct($productId): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    /**
     * Set specific quantity.
     */
    public function quantity($quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => max(1, $quantity),
        ]);
    }
}