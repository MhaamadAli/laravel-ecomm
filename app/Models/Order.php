<?php

/**
 * Order Model - Handles customer orders and order management
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Models/Order.php
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class Order extends Model
{
    use HasFactory;

    // Define order statuses as constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'total_amount',
        'shipping_address',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'shipping_address' => 'array',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'status_label',
        'items_count',
        'can_be_cancelled'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Generate order number when creating
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
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
     * Define relationship with order items
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('order_number', $orderNumber)->exists());
        
        return $orderNumber;
    }

    /**
     * Get all available order statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get status labels for display
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get status label attribute
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? 'Unknown';
    }

    /**
     * Get items count attribute
     */
    public function getItemsCountAttribute(): int
    {
        return $this->orderItems()->sum('quantity');
    }

    /**
     * Check if order can be cancelled
     */
    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Calculate total from order items
     */
    public function calculateTotal(): float
    {
        return $this->orderItems->sum(function($item) {
            return $item->quantity * $item->price;
        });
    }

    /**
     * Recalculate and update total amount
     */
    public function updateTotal(): void
    {
        $this->update(['total_amount' => $this->calculateTotal()]);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for recent orders
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for orders by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Update order status with notification
     */
    public function updateStatus($newStatus, $sendNotification = true): bool
    {
        $oldStatus = $this->status;
        
        if (!in_array($newStatus, self::getStatuses())) {
            return false;
        }

        $this->update(['status' => $newStatus]);

        // Send email notification if enabled
        if ($sendNotification && $oldStatus !== $newStatus) {
            $this->sendStatusUpdateNotification($oldStatus, $newStatus);
        }

        return true;
    }

    /**
     * Cancel the order
     */
    public function cancel(): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        // Restore product stock
        foreach ($this->orderItems as $item) {
            $item->product->increaseStock($item->quantity);
        }

        return $this->updateStatus(self::STATUS_CANCELLED);
    }

    /**
     * Check if order is in a specific status
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isShipped(): bool
    {
        return $this->status === self::STATUS_SHIPPED;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Send status update notification email
     */
    protected function sendStatusUpdateNotification($oldStatus, $newStatus): void
    {
        try {
            // TODO: Implement email notification
            // This would typically use a Mail class or notification
            // For now, we'll just log the status change
            \Log::info("Order {$this->order_number} status changed from {$oldStatus} to {$newStatus}");
        } catch (\Exception $e) {
            \Log::error("Failed to send order status notification: " . $e->getMessage());
        }
    }

    /**
     * Get formatted shipping address
     */
    public function getFormattedShippingAddress(): string
    {
        if (!$this->shipping_address) {
            return '';
        }

        $address = $this->shipping_address;
        return implode(', ', array_filter([
            $address['name'] ?? '',
            $address['address_line_1'] ?? '',
            $address['address_line_2'] ?? '',
            $address['city'] ?? '',
            $address['state'] ?? '',
            $address['postal_code'] ?? '',
            $address['country'] ?? '',
        ]));
    }
}