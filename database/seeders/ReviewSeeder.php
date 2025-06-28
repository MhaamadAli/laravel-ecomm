<?php

/**
 * Review Seeder - Seeds the database with sample product reviews
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/seeders/ReviewSeeder.php
 */

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', User::ROLE_USER)->get();
        $products = Product::where('is_active', true)->get();
        
        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->warn('No users or products found. Please run UserSeeder and ProductSeeder first.');
            return;
        }

        // Create reviews for users who have ordered products
        $deliveredOrders = Order::where('status', Order::STATUS_DELIVERED)->with('orderItems.product')->get();
        
        foreach ($deliveredOrders as $order) {
            foreach ($order->orderItems as $orderItem) {
                // 40% chance of leaving a review
                if (rand(1, 100) <= 40) {
                    // Check if review already exists
                    if (!Review::where('user_id', $order->user_id)
                               ->where('product_id', $orderItem->product_id)
                               ->exists()) {
                        
                        Review::factory()
                              ->forUser($order->user_id)
                              ->forProduct($orderItem->product_id)
                              ->approved()
                              ->create();
                    }
                }
            }
        }

        // Create additional random reviews
        foreach ($users->random(15) as $user) {
            $userProducts = $products->random(rand(1, 5));
            
            foreach ($userProducts as $product) {
                // Check if review already exists
                if (!Review::where('user_id', $user->id)
                           ->where('product_id', $product->id)
                           ->exists()) {
                    
                    Review::factory()
                          ->forUser($user->id)
                          ->forProduct($product->id)
                          ->approved()
                          ->create();
                }
            }
        }

        // Create some pending reviews (not approved yet)
        Review::factory(10)->pending()->create();

        $this->command->info('Reviews seeded successfully!');
    }
}