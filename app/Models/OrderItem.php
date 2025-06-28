<?php

/**
 * OrderItem Model - Handles individual items within an order
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Models/OrderItem.php
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'total',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-calculate total when creating or updating
        static::saving(function ($orderItem) {
            $orderItem->total = $orderItem->calculateTotal();
        });
    }

    /**
     * Define relationship with order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Define relationship with product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate item total (quantity * price)
     */
    public function calculateTotal(): float
    {
        return $this->quantity * $this->price;
    }

    /**
     * Get the subtotal for this order item
     */
    public function getSubtotalAttribute(): float
    {
        return $this->total;
    }

    /**
     * Update quantity and recalculate total
     */
    public function updateQuantity(int $quantity): void
    {
        $this->update([
            'quantity' => $quantity,
            'total' => $quantity * $this->price
        ]);
    }
}