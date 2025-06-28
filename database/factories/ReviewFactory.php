<?php

/**
 * Review Factory - Creates fake review data for testing
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/factories/ReviewFactory.php
 */

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rating = fake()->numberBetween(1, 5);
        
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'rating' => $rating,
            'title' => fake()->optional(0.7)->sentence(rand(3, 8)),
            'comment' => fake()->optional(0.8)->paragraph(),
            'is_approved' => fake()->boolean(85), // 85% chance of being approved
        ];
    }

    /**
     * Indicate that the review is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
        ]);
    }

    /**
     * Indicate that the review is not approved.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }

    /**
     * Set a specific rating.
     */
    public function rating($rating): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => max(1, min(5, $rating)),
        ]);
    }

    /**
     * Create a positive review (4-5 stars).
     */
    public function positive(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->paragraph(),
        ]);
    }

    /**
     * Create a negative review (1-2 stars).
     */
    public function negative(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => fake()->numberBetween(1, 2),
            'comment' => fake()->paragraph(),
        ]);
    }

    /**
     * Set the user for this review.
     */
    public function forUser($userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Set the product for this review.
     */
    public function forProduct($productId): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }
}