<?php

/**
 * Product Factory - Creates fake product data for testing
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/factories/ProductFactory.php
 */

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(rand(2, 4), true);
        $price = fake()->randomFloat(2, 10, 500);
        $salePrice = fake()->boolean(30) ? fake()->randomFloat(2, $price * 0.5, $price * 0.9) : null;
        
        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name) . '-' . fake()->unique()->randomNumber(4),
            'description' => fake()->paragraphs(rand(2, 4), true),
            'short_description' => fake()->optional(0.8)->sentence(rand(10, 20)),
            'price' => $price,
            'sale_price' => $salePrice,
            'sku' => fake()->boolean(80) ? fake()->unique()->regexify('[A-Z]{2}[0-9]{6}') : null,
            'stock_quantity' => fake()->numberBetween(0, 100),
            'category_id' => Category::factory(),
            'images' => fake()->optional(0.8)->randomElements([
                fake()->imageUrl(600, 600, 'business'),
                fake()->imageUrl(600, 600, 'business'),
                fake()->imageUrl(600, 600, 'business'),
            ], rand(1, 3)),
            'is_active' => fake()->boolean(85), // 85% chance of being active
            'featured' => fake()->boolean(20), // 20% chance of being featured
        ];
    }

    /**
     * Indicate that the product is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the product is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }

    /**
     * Indicate that the product is on sale.
     */
    public function onSale(): static
    {
        return $this->state(function (array $attributes) {
            $price = $attributes['price'] ?? fake()->randomFloat(2, 10, 500);
            return [
                'sale_price' => fake()->randomFloat(2, $price * 0.5, $price * 0.9),
            ];
        });
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    /**
     * Indicate that the product has low stock.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Set the product category.
     */
    public function forCategory($categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }
}