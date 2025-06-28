<?php

/**
 * Order Item Factory - Creates fake order item data for testing
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/factories/OrderItemFactory.php
 */

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $price = fake()->randomFloat(2, 10, 200);
        
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'price' => $price,
            'total' => $quantity * $price,
        ];
    }

    /**
     * Set the order for this item.
     */
    public function forOrder($orderId): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Set the product for this item.
     */
    public function forProduct($productId, $productPrice = null): static
    {
        return $this->state(function (array $attributes) use ($productId, $productPrice) {
            $quantity = $attributes['quantity'] ?? fake()->numberBetween(1, 5);
            $price = $productPrice ?? fake()->randomFloat(2, 10, 200);
            
            return [
                'product_id' => $productId,
                'price' => $price,
                'total' => $quantity * $price,
            ];
        });
    }

    /**
     * Set specific quantity.
     */
    public function quantity($quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $price = $attributes['price'] ?? fake()->randomFloat(2, 10, 200);
            
            return [
                'quantity' => $quantity,
                'total' => $quantity * $price,
            ];
        });
    }
}