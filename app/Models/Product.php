<?php

/**
 * Product Model - Handles product information and inventory
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Models/Product.php
 * 
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     title="Product",
 *     description="Product model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="iPhone 15"),
 *     @OA\Property(property="slug", type="string", example="iphone-15"),
 *     @OA\Property(property="description", type="string", example="Latest iPhone with advanced features"),
 *     @OA\Property(property="short_description", type="string", example="Latest iPhone"),
 *     @OA\Property(property="price", type="number", format="float", example=999.99),
 *     @OA\Property(property="sale_price", type="number", format="float", nullable=true, example=899.99),
 *     @OA\Property(property="sku", type="string", example="IP15-001"),
 *     @OA\Property(property="stock_quantity", type="integer", example=50),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="images", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="featured", type="boolean", example=false),
 *     @OA\Property(property="effective_price", type="number", format="float", example=899.99),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
 *     @OA\Property(property="reviews_count", type="integer", example=25),
 *     @OA\Property(property="is_in_stock", type="boolean", example=true),
 *     @OA\Property(property="discount_percentage", type="integer", nullable=true, example=10),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'sale_price',
        'sku',
        'stock_quantity',
        'category_id',
        'images',
        'is_active',
        'featured',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'images' => 'array',
        'is_active' => 'boolean',
        'featured' => 'boolean',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'effective_price',
        'average_rating',
        'reviews_count',
        'is_in_stock',
        'discount_percentage'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug when creating
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            
            // Auto-generate SKU if not provided
            if (empty($product->sku)) {
                $product->sku = 'SKU-' . strtoupper(Str::random(8));
            }
        });

        // Update slug when name changes
        static::updating(function ($product) {
            if ($product->isDirty('name')) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    /**
     * Define relationship with category
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Define relationship with reviews
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Define relationship with approved reviews only
     */
    public function approvedReviews()
    {
        return $this->hasMany(Review::class)->where('is_approved', true);
    }

    /**
     * Define relationship with cart items
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Define relationship with order items
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Define relationship with wishlists
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Scope for active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope for products in stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    /**
     * Scope for searching products
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function (Builder $query) use ($search) {
            $query->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%")
                  ->orWhere('short_description', 'ILIKE', "%{$search}%")
                  ->orWhere('sku', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * Scope for filtering by price range
     */
    public function scopePriceRange($query, $minPrice = null, $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }
        
        return $query;
    }

    /**
     * Scope for filtering by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Get effective price (sale price if available, otherwise regular price)
     */
    public function getEffectivePriceAttribute()
    {
        return $this->sale_price ?? $this->price;
    }

    /**
     * Check if product is in stock
     */
    public function getIsInStockAttribute(): bool
    {
        return $this->stock_quantity > 0;
    }

    /**
     * Check if specific quantity is available
     */
    public function isInStock($quantity = 1): bool
    {
        return $this->stock_quantity >= $quantity;
    }

    /**
     * Get average rating from approved reviews
     */
    public function getAverageRatingAttribute()
    {
        return $this->approvedReviews()->avg('rating') ?? 0;
    }

    /**
     * Get total reviews count
     */
    public function getReviewsCountAttribute(): int
    {
        return $this->approvedReviews()->count();
    }

    /**
     * Get discount percentage if on sale
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->sale_price || $this->sale_price >= $this->price) {
            return null;
        }
        
        return round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    /**
     * Check if product is on sale
     */
    public function isOnSale(): bool
    {
        return $this->sale_price && $this->sale_price < $this->price;
    }

    /**
     * Get the main product image
     */
    public function getMainImageAttribute(): ?string
    {
        return $this->images[0] ?? null;
    }

    /**
     * Reduce stock quantity (for order processing)
     */
    public function reduceStock($quantity): bool
    {
        if (!$this->isInStock($quantity)) {
            return false;
        }
        
        $this->decrement('stock_quantity', $quantity);
        return true;
    }

    /**
     * Increase stock quantity (for order cancellation)
     */
    public function increaseStock($quantity): void
    {
        $this->increment('stock_quantity', $quantity);
    }

    /**
     * Get route key name for URL binding
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get related products (same category, excluding current product)
     */
    public function getRelatedProducts($limit = 4)
    {
        return self::where('category_id', $this->category_id)
                   ->where('id', '!=', $this->id)
                   ->active()
                   ->inStock()
                   ->limit($limit)
                   ->get();
    }
}