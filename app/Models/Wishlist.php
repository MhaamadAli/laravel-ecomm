<?php

/**
 * Wishlist Model - Handles user wishlist items
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Models/Wishlist.php
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'product_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Define relationship with user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define relationship with product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for user's wishlist
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for active products only
     */
    public function scopeWithActiveProducts($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * Scope for in-stock products only
     */
    public function scopeWithInStockProducts($query)
    {
        return $query->whereHas('product', function ($q) {
            $q->where('stock_quantity', '>', 0);
        });
    }

    /**
     * Scope for recent wishlist items
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Check if product is already in user's wishlist
     */
    public static function isInWishlist($userId, $productId): bool
    {
        return self::where('user_id', $userId)
                   ->where('product_id', $productId)
                   ->exists();
    }

    /**
     * Add product to user's wishlist
     */
    public static function addToWishlist($userId, $productId): ?self
    {
        // Check if already in wishlist
        if (self::isInWishlist($userId, $productId)) {
            return null;
        }

        return self::create([
            'user_id' => $userId,
            'product_id' => $productId
        ]);
    }

    /**
     * Remove product from user's wishlist
     */
    public static function removeFromWishlist($userId, $productId): bool
    {
        return self::where('user_id', $userId)
                   ->where('product_id', $productId)
                   ->delete() > 0;
    }

    /**
     * Move to cart (if product is available)
     */
    public function moveToCart(int $quantity = 1): bool
    {
        if (!$this->product || !$this->product->is_active || !$this->product->isInStock($quantity)) {
            return false;
        }

        // Check if already in cart
        $existingCartItem = CartItem::where('user_id', $this->user_id)
                                   ->where('product_id', $this->product_id)
                                   ->first();

        if ($existingCartItem) {
            $existingCartItem->increaseQuantity($quantity);
        } else {
            CartItem::create([
                'user_id' => $this->user_id,
                'product_id' => $this->product_id,
                'quantity' => $quantity
            ]);
        }

        // Remove from wishlist
        $this->delete();
        
        return true;
    }

    /**
     * Check if wishlist item's product is available
     */
    public function isProductAvailable(): bool
    {
        return $this->product && 
               $this->product->is_active && 
               $this->product->stock_quantity > 0;
    }
}