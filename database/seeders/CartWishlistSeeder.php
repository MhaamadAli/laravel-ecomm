<?php

/**
 * Cart and Wishlist Seeder - Seeds the database with sample cart items and wishlists
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/database/seeders/CartWishlistSeeder.php
 */

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\Wishlist;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CartWishlistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', User::ROLE_USER)->get();
        $products = Product::where('is_active', true)->where('stock_quantity', '>', 0)->get();
        
        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->warn('No users or products found. Please run UserSeeder and ProductSeeder first.');
            return;
        }

        // Create cart items for some users
        foreach ($users->random(10) as $user) {
            $cartProducts = $products->random(rand(1, 4));
            
            foreach ($cartProducts as $product) {
                // Check if product is already in cart
                if (!CartItem::where('user_id', $user->id)
                             ->where('product_id', $product->id)
                             ->exists()) {
                    
                    CartItem::factory()
                           ->forUser($user->id)
                           ->forProduct($product->id)
                           ->quantity(rand(1, min(3, $product->stock_quantity)))
                           ->create();
                }
            }
        }

        // Create wishlist items for some users
        foreach ($users->random(15) as $user) {
            $wishlistProducts = $products->random(rand(2, 8));
            
            foreach ($wishlistProducts as $product) {
                // Check if product is already in wishlist
                if (!Wishlist::where('user_id', $user->id)
                             ->where('product_id', $product->id)
                             ->exists()) {
                    
                    Wishlist::factory()
                           ->forUser($user->id)
                           ->forProduct($product->id)
                           ->create();
                }
            }
        }

        $this->command->info('Cart items and wishlists seeded successfully!');
    }
}