<?php

/**
 * Category Factory - Creates fake category data for testing
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/factories/CategoryFactory.php
 */

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(rand(1, 3), true);
        
        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional(0.7)->paragraph(),
            'image' => fake()->optional(0.5)->imageUrl(300, 300, 'business'),
            'parent_id' => null, // Will be set in seeder for subcategories
            'is_active' => fake()->boolean(90), // 90% chance of being active
        ];
    }

    /**
     * Indicate that the category is a root category.
     */
    public function root(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
        ]);
    }

    /**
     * Indicate that the category is a subcategory.
     */
    public function subcategory($parentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId ?? Category::factory()->root(),
        ]);
    }

    /**
     * Indicate that the category is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}