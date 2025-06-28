<?php

/**
 * Review Model - Handles product reviews and ratings
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Models/Review.php
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'title',
        'comment',
        'is_approved',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_approved' => 'boolean',
        'rating' => 'integer',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'user_name'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Set default approval status to false for new reviews
        static::creating(function ($review) {
            if (is_null($review->is_approved)) {
                $review->is_approved = false;
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
     * Scope for approved reviews
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope for pending reviews
     */
    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    /**
     * Scope for filtering by rating
     */
    public function scopeRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope for filtering by minimum rating
     */
    public function scopeMinRating($query, $minRating)
    {
        return $query->where('rating', '>=', $minRating);
    }

    /**
     * Scope for recent reviews
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get user name attribute
     */
    public function getUserNameAttribute(): string
    {
        return $this->user ? $this->user->name : 'Anonymous';
    }

    /**
     * Approve the review
     */
    public function approve(): void
    {
        $this->update(['is_approved' => true]);
    }

    /**
     * Reject the review
     */
    public function reject(): void
    {
        $this->update(['is_approved' => false]);
    }

    /**
     * Check if review is approved
     */
    public function isApproved(): bool
    {
        return $this->is_approved;
    }

    /**
     * Get star rating as array for display
     */
    public function getStarsArray(): array
    {
        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars[] = $i <= $this->rating;
        }
        return $stars;
    }

    /**
     * Validate rating range
     */
    public static function validateRating($rating): bool
    {
        return is_numeric($rating) && $rating >= 1 && $rating <= 5;
    }
}