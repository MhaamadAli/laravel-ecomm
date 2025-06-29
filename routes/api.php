<?php

/**
 * API Routes - Defines all API endpoints for the e-commerce platform
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/routes/api.php
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import all controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\FileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public Product Routes
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/featured', [ProductController::class, 'featured']);
    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/{slug}', [ProductController::class, 'show']);
    Route::get('/{productId}/reviews', [ReviewController::class, 'index']);
    Route::get('/{productId}/can-review', [ReviewController::class, 'canReview'])->middleware('sanctum.auth');
    Route::get('/{productId}/related', [ProductController::class, 'related']);
});

// Public Category Routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/tree', [CategoryController::class, 'tree']);
    Route::get('/{slug}', [CategoryController::class, 'show']);
    Route::get('/{slug}/products', [CategoryController::class, 'products']);
});

// Protected User Routes (Require Authentication)
Route::middleware('sanctum.auth')->group(function () {
    
    // User Authentication Routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);
    
    // Shopping Cart Routes
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::get('/summary', [CartController::class, 'summary']);
        Route::post('/', [CartController::class, 'store']);
        Route::put('/{id}', [CartController::class, 'update']);
        Route::delete('/{id}', [CartController::class, 'destroy']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::post('/validate', [CartController::class, 'validate']);
    });
    
    // Order Routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/stats', [OrderController::class, 'stats']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{orderNumber}', [OrderController::class, 'show']);
        Route::post('/{orderNumber}/cancel', [OrderController::class, 'cancel']);
    });
    
    // Wishlist Routes
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::delete('/{id}', [WishlistController::class, 'destroy']);
        Route::delete('/product/{productId}', [WishlistController::class, 'removeByProduct']);
        Route::delete('/', [WishlistController::class, 'clear']);
        Route::post('/{id}/move-to-cart', [WishlistController::class, 'moveToCart']);
        Route::get('/check/{productId}', [WishlistController::class, 'check']);
    });
    
    // Review Routes
    Route::get('/reviews/my-reviews', [ReviewController::class, 'myReviews']);
    Route::post('/products/{productId}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    
    // File Upload Routes
    Route::prefix('upload')->group(function () {
        Route::post('/image', [FileController::class, 'uploadImage']);
        Route::post('/images/multiple', [FileController::class, 'uploadMultipleImages']);
        Route::delete('/image', [FileController::class, 'deleteImage']);
    });
});

// Admin Routes (Require Admin Role)
Route::middleware(['sanctum.auth', 'admin'])->prefix('admin')->group(function () {
    
    // Admin Product Management
    Route::prefix('products')->group(function () {
        Route::get('/', [AdminProductController::class, 'index']);
        Route::get('/stats', [AdminProductController::class, 'stats']);
        Route::post('/bulk-update', [AdminProductController::class, 'bulkUpdate']);
        Route::get('/{id}', [AdminProductController::class, 'show']);
        Route::post('/', [AdminProductController::class, 'store']);
        Route::put('/{id}', [AdminProductController::class, 'update']);
        Route::delete('/{id}', [AdminProductController::class, 'destroy']);
    });
    
    // Admin Category Management
    Route::prefix('categories')->group(function () {
        Route::get('/', [AdminCategoryController::class, 'index']);
        Route::get('/tree', [AdminCategoryController::class, 'tree']);
        Route::get('/stats', [AdminCategoryController::class, 'stats']);
        Route::get('/{id}', [AdminCategoryController::class, 'show']);
        Route::post('/', [AdminCategoryController::class, 'store']);
        Route::put('/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('/{id}', [AdminCategoryController::class, 'destroy']);
    });
    
    // Admin Order Management
    Route::prefix('orders')->group(function () {
        Route::get('/', [AdminOrderController::class, 'index']);
        Route::get('/stats', [AdminOrderController::class, 'stats']);
        Route::post('/bulk-update', [AdminOrderController::class, 'bulkUpdate']);
        Route::get('/{orderNumber}', [AdminOrderController::class, 'show']);
        Route::put('/{orderNumber}', [AdminOrderController::class, 'update']);
    });
    
    // Admin User Management
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('/stats', [AdminUserController::class, 'stats']);
        Route::get('/{id}', [AdminUserController::class, 'show']);
        Route::put('/{id}', [AdminUserController::class, 'update']);
        Route::delete('/{id}', [AdminUserController::class, 'destroy']);
    });
});



// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'message' => 'API endpoint not found'
    ], 404);
});