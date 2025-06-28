<?php

/**
 * Product Seeder - Seeds the database with sample products
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/seeders/ProductSeeder.php
 */

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all categories
        $categories = Category::all();
        
        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run CategorySeeder first.');
            return;
        }

        // Sample products with realistic data
        $sampleProducts = [
            // Electronics
            [
                'name' => 'iPhone 15 Pro',
                'description' => 'Latest iPhone with A17 Pro chip, titanium design, and advanced camera system.',
                'short_description' => 'Premium smartphone with cutting-edge technology.',
                'price' => 999.00,
                'sale_price' => 899.00,
                'stock_quantity' => 50,
                'featured' => true,
                'category' => 'Smartphones'
            ],
            [
                'name' => 'MacBook Air M2',
                'description' => 'Incredibly thin and light laptop with M2 chip, all-day battery life, and stunning display.',
                'short_description' => 'Ultra-portable laptop with M2 chip.',
                'price' => 1199.00,
                'stock_quantity' => 30,
                'featured' => true,
                'category' => 'Laptops'
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'description' => 'Industry-leading noise canceling headphones with exceptional sound quality.',
                'short_description' => 'Premium noise-canceling headphones.',
                'price' => 399.00,
                'sale_price' => 349.00,
                'stock_quantity' => 75,
                'category' => 'Audio'
            ],
            
            // Clothing
            [
                'name' => 'Premium Cotton T-Shirt',
                'description' => 'Comfortable 100% organic cotton t-shirt in various colors and sizes.',
                'short_description' => 'Soft organic cotton t-shirt.',
                'price' => 29.99,
                'stock_quantity' => 200,
                'category' => 'Men\'s Clothing'
            ],
            [
                'name' => 'Designer Jeans',
                'description' => 'Premium denim jeans with perfect fit and long-lasting quality.',
                'short_description' => 'Premium quality denim jeans.',
                'price' => 89.99,
                'sale_price' => 69.99,
                'stock_quantity' => 150,
                'category' => 'Women\'s Clothing'
            ],
            [
                'name' => 'Running Sneakers',
                'description' => 'Lightweight running shoes with advanced cushioning and breathable design.',
                'short_description' => 'Comfortable running shoes.',
                'price' => 129.99,
                'stock_quantity' => 100,
                'featured' => true,
                'category' => 'Shoes'
            ],
            
            // Home & Garden
            [
                'name' => 'Ergonomic Office Chair',
                'description' => 'Comfortable office chair with lumbar support and adjustable height.',
                'short_description' => 'Ergonomic office chair with lumbar support.',
                'price' => 299.99,
                'sale_price' => 249.99,
                'stock_quantity' => 40,
                'category' => 'Furniture'
            ],
            [
                'name' => 'Stainless Steel Cookware Set',
                'description' => 'Professional-grade cookware set with non-stick coating and heat distribution.',
                'short_description' => '10-piece stainless steel cookware set.',
                'price' => 199.99,
                'stock_quantity' => 60,
                'category' => 'Kitchen'
            ],
            
            // Sports & Outdoors
            [
                'name' => 'Yoga Mat Premium',
                'description' => 'High-quality yoga mat with excellent grip and cushioning for all yoga practices.',
                'short_description' => 'Premium yoga mat with superior grip.',
                'price' => 49.99,
                'stock_quantity' => 120,
                'category' => 'Fitness'
            ],
            [
                'name' => 'Mountain Bike Helmet',
                'description' => 'Safety-certified mountain bike helmet with ventilation and adjustable fit.',
                'short_description' => 'Lightweight mountain bike helmet.',
                'price' => 79.99,
                'stock_quantity' => 80,
                'category' => 'Outdoor Sports'
            ],
            
            // Books & Media
            [
                'name' => 'Programming Best Practices',
                'description' => 'Comprehensive guide to modern programming techniques and best practices.',
                'short_description' => 'Essential guide for programmers.',
                'price' => 39.99,
                'stock_quantity' => 90,
                'category' => 'Educational'
            ],
            [
                'name' => 'Classic Literature Collection',
                'description' => 'Beautiful hardcover collection of classic literature masterpieces.',
                'short_description' => 'Hardcover classic literature set.',
                'price' => 89.99,
                'sale_price' => 69.99,
                'stock_quantity' => 45,
                'featured' => true,
                'category' => 'Fiction'
            ],
        ];

        // Create sample products
        foreach ($sampleProducts as $productData) {
            $category = $categories->where('name', $productData['category'])->first();
            
            if ($category) {
                Product::create([
                    'name' => $productData['name'],
                    'slug' => Str::slug($productData['name']) . '-' . rand(1000, 9999),
                    'description' => $productData['description'],
                    'short_description' => $productData['short_description'],
                    'price' => $productData['price'],
                    'sale_price' => $productData['sale_price'] ?? null,
                    'sku' => 'SKU' . strtoupper(Str::random(6)),
                    'stock_quantity' => $productData['stock_quantity'],
                    'category_id' => $category->id,
                    'images' => [
                        'https://via.placeholder.com/600x600/6366F1/ffffff?text=' . urlencode(substr($productData['name'], 0, 5)),
                        'https://via.placeholder.com/600x600/8B5CF6/ffffff?text=' . urlencode(substr($productData['name'], 0, 5)),
                        'https://via.placeholder.com/600x600/06B6D4/ffffff?text=' . urlencode(substr($productData['name'], 0, 5)),
                    ],
                    'is_active' => true,
                    'featured' => $productData['featured'] ?? false,
                ]);
            }
        }

        // Create additional random products using factory
        $activeCategories = Category::where('is_active', true)->get();
        
        foreach ($activeCategories as $category) {
            // Create 3-8 products per category
            Product::factory(rand(3, 8))
                   ->forCategory($category->id)
                   ->active()
                   ->create();
        }

        // Create some featured products
        Product::factory(10)->active()->featured()->create();
        
        // Create some sale products
        Product::factory(15)->active()->onSale()->create();
        
        // Create some out of stock products
        Product::factory(5)->active()->outOfStock()->create();

        $this->command->info('Products seeded successfully!');
    }
}