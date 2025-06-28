<?php

/**
 * Category Model - Handles product categories with hierarchical structure
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Models/Category.php
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_url',
        'parent_id',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'products_count',
        'hierarchy_path'
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug when creating
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        // Update slug when name changes
        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Define relationship with products
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Define self-referencing relationship for parent category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Define relationship for subcategories
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get all active children recursively
     */
    public function allChildren()
    {
        return $this->children()->with('allChildren')->where('is_active', true);
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for root categories (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for categories with children
     */
    public function scopeWithChildren($query)
    {
        return $query->has('children');
    }

    /**
     * Get category hierarchy path as string
     */
    public function getHierarchyPathAttribute(): string
    {
        $path = [];
        $category = $this;
        
        while ($category) {
            array_unshift($path, $category->name);
            $category = $category->parent;
        }
        
        return implode(' > ', $path);
    }

    /**
     * Get category hierarchy as array
     */
    public function getHierarchy(): array
    {
        $hierarchy = [];
        $category = $this;
        
        while ($category) {
            array_unshift($hierarchy, [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug
            ]);
            $category = $category->parent;
        }
        
        return $hierarchy;
    }

    /**
     * Get products count including subcategories
     */
    public function getProductsCountAttribute(): int
    {
        static $calculating = [];
        
        // Prevent infinite recursion
        if (isset($calculating[$this->id])) {
            return $this->products()->where('is_active', true)->count();
        }
        
        $calculating[$this->id] = true;
        
        $count = $this->products()->where('is_active', true)->count();
        
        // Add products from direct children only (not recursive)
        $count += $this->children()
                      ->where('is_active', true)
                      ->withCount(['products' => function($query) {
                          $query->where('is_active', true);
                      }])
                      ->get()
                      ->sum('products_count');
        
        unset($calculating[$this->id]);
        
        return $count;
    }

    /**
     * Check if this category has any subcategories
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if this category is a root category
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Get all descendant category IDs
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }
        
        return $ids;
    }

    /**
     * Get route key name for URL binding
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}