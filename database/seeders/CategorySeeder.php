<?php

/**
 * Category Seeder - Seeds the database with sample categories
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/seeders/CategorySeeder.php
 */

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create main categories
        $mainCategories = [
            [
                'name' => 'Electronics',
                'description' => 'Electronic devices and gadgets',
                'subcategories' => ['Smartphones', 'Laptops', 'Tablets', 'Audio', 'Gaming']
            ],
            [
                'name' => 'Clothing & Fashion',
                'description' => 'Apparel and fashion accessories',
                'subcategories' => ['Men\'s Clothing', 'Women\'s Clothing', 'Shoes', 'Fashion Accessories', 'Bags']
            ],
            [
                'name' => 'Home & Garden',
                'description' => 'Home improvement and garden supplies',
                'subcategories' => ['Furniture', 'Kitchen', 'Bathroom', 'Garden Tools', 'Decor']
            ],
            [
                'name' => 'Sports & Outdoors',
                'description' => 'Sports equipment and outdoor gear',
                'subcategories' => ['Fitness', 'Outdoor Sports', 'Team Sports', 'Water Sports', 'Winter Sports']
            ],
            [
                'name' => 'Books & Media',
                'description' => 'Books, movies, music and educational materials',
                'subcategories' => ['Fiction', 'Non-Fiction', 'Educational', 'Movies', 'Music']
            ],
            [
                'name' => 'Health & Beauty',
                'description' => 'Health and beauty products',
                'subcategories' => ['Skincare', 'Makeup', 'Hair Care', 'Health Supplements', 'Personal Care']
            ],
            [
                'name' => 'Automotive',
                'description' => 'Car parts and automotive accessories',
                'subcategories' => ['Car Parts', 'Tools', 'Car Accessories', 'Maintenance', 'Car Electronics']
            ],
            [
                'name' => 'Toys & Games',
                'description' => 'Toys and games for all ages',
                'subcategories' => ['Educational Toys', 'Action Figures', 'Board Games', 'Video Games', 'Outdoor Toys']
            ],
        ];

        foreach ($mainCategories as $categoryData) {
            // Create main category
            $category = Category::create([
                'name' => $categoryData['name'],
                'slug' => Str::slug($categoryData['name']),
                'description' => $categoryData['description'],
                'image' => 'https://via.placeholder.com/300x300/4F46E5/ffffff?text=' . urlencode(substr($categoryData['name'], 0, 3)),
                'parent_id' => null,
                'is_active' => true,
            ]);

            // Create subcategories
            foreach ($categoryData['subcategories'] as $subCategoryName) {
                Category::create([
                    'name' => $subCategoryName,
                    'slug' => Str::slug($subCategoryName),
                    'description' => "All {$subCategoryName} products",
                    'image' => 'https://via.placeholder.com/300x300/10B981/ffffff?text=' . urlencode(substr($subCategoryName, 0, 3)),
                    'parent_id' => $category->id,
                    'is_active' => true,
                ]);
            }
        }

        // Create some additional random categories using factory
        Category::factory(10)->create();

        $this->command->info('Categories seeded successfully!');
    }
}