<?php

/**
 * Order Factory - Creates fake order data for testing
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/factories/OrderFactory.php
 */

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = [Order::STATUS_PENDING, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_CANCELLED];
        
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . fake()->unique()->randomNumber(8),
            'status' => fake()->randomElement($statuses),
            'total_amount' => fake()->randomFloat(2, 20, 500),
            'shipping_address' => [
                'name' => fake()->name(),
                'address_line_1' => fake()->streetAddress(),
                'address_line_2' => fake()->optional(0.3)->secondaryAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'postal_code' => fake()->postcode(),
                'country' => fake()->country(),
                'phone' => fake()->optional(0.7)->phoneNumber(),
            ],
            'notes' => fake()->optional(0.3)->sentence(),
            'admin_notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PENDING,
        ]);
    }

    /**
     * Indicate that the order is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_PROCESSING,
        ]);
    }

    /**
     * Indicate that the order is shipped.
     */
    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_SHIPPED,
        ]);
    }

    /**
     * Indicate that the order is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_DELIVERED,
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Order::STATUS_CANCELLED,
        ]);
    }

    /**
     * Set the order user.
     */
    public function forUser($userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }
}