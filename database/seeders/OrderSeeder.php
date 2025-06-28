<?php

/**
 * Order Seeder - Seeds the database with sample orders and order items
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/seeders/OrderSeeder.php
 */

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
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

        // Create orders for each user
        foreach ($users as $user) {
            // Create 1-5 orders per user
            $orderCount = rand(1, 5);
            
            for ($i = 0; $i < $orderCount; $i++) {
                // Create order
                $order = Order::factory()->forUser($user->id)->create();
                
                // Create 1-4 order items per order
                $itemCount = rand(1, 4);
                $orderTotal = 0;
                
                $selectedProducts = $products->random($itemCount);
                
                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 3);
                    $price = $product->effective_price;
                    $total = $quantity * $price;
                    
                    OrderItem::factory()
                           ->forOrder($order->id)
                           ->forProduct($product->id, $price)
                           ->quantity($quantity)
                           ->create();
                    
                    $orderTotal += $total;
                }
                
                // Update order total
                $order->update(['total_amount' => $orderTotal]);
            }
        }

        // Create some additional random orders
        Order::factory(20)
             ->create()
             ->each(function ($order) {
                 $products = Product::where('is_active', true)->inRandomOrder()->take(rand(1, 5))->get();
                 $orderTotal = 0;
                 
                 foreach ($products as $product) {
                     $quantity = rand(1, 3);
                     $price = $product->effective_price;
                     $total = $quantity * $price;
                     
                     OrderItem::factory()
                            ->forOrder($order->id)
                            ->forProduct($product->id, $price)
                            ->quantity($quantity)
                            ->create();
                     
                     $orderTotal += $total;
                 }
                 
                 $order->update(['total_amount' => $orderTotal]);
             });

        $this->command->info('Orders and order items seeded successfully!');
    }
}