<?php

/**
 * CartItem Model - Handles shopping cart items
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Models/CartItem.php
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'subtotal'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure quantity is at least 1
        static::saving(function ($cartItem) {
            if ($cartItem->quantity < 1) {
                $cartItem->quantity = 1;
            }
        });
    }

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
     * Get subtotal for this cart item
     */
    public function getSubtotalAttribute(): float
    {
        return $this->quantity * ($this->product ? $this->product->effective_price : 0);
    }

    /**
     * Get subtotal (method version for direct calls)
     */
    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    /**
     * Scope for user's cart
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
     * Update quantity
     */
    public function updateQuantity(int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }

        // Check if requested quantity is available
        if ($this->product && !$this->product->isInStock($quantity)) {
            return false;
        }

        $this->update(['quantity' => $quantity]);
        return true;
    }

    /**
     * Increase quantity by amount
     */
    public function increaseQuantity(int $amount = 1): bool
    {
        return $this->updateQuantity($this->quantity + $amount);
    }

    /**
     * Decrease quantity by amount
     */
    public function decreaseQuantity(int $amount = 1): bool
    {
        return $this->updateQuantity($this->quantity - $amount);
    }

    /**
     * Check if product is still available
     */
    public function isAvailable(): bool
    {
        return $this->product && 
               $this->product->is_active && 
               $this->product->isInStock($this->quantity);
    }

    /**
     * Get maximum available quantity for this product
     */
    public function getMaxQuantity(): int
    {
        return $this->product ? $this->product->stock_quantity : 0;
    }
}