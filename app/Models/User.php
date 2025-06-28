<?php

/**
 * User Model - Handles user authentication and profile management
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Models/User.php
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+1234567890"),
 *     @OA\Property(property="role", type="string", enum={"user", "admin"}, example="user"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Define user roles as constants
    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Check if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user has regular user role
     */
    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * Get all available user roles
     */
    public static function getRoles(): array
    {
        return [
            self::ROLE_USER,
            self::ROLE_ADMIN,
        ];
    }

    /**
     * Define relationship with orders
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Define relationship with cart items
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Define relationship with wishlist items
     */
    public function wishlist()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Define relationship with reviews
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get user's cart total items count
     */
    public function getCartItemsCountAttribute(): int
    {
        return $this->cartItems()->sum('quantity');
    }

    /**
     * Get user's cart total amount
     */
    public function getCartTotalAttribute(): float
    {
        return $this->cartItems()->with('product')->get()->sum(function ($cartItem) {
            return $cartItem->quantity * $cartItem->product->getEffectivePrice();
        });
    }

    /**
     * Get user's wishlist count
     */
    public function getWishlistCountAttribute(): int
    {
        return $this->wishlist()->count();
    }

    /**
     * Get user's total orders count
     */
    public function getOrdersCountAttribute(): int
    {
        return $this->orders()->count();
    }
}