<?php

/**
 * Wishlist Controller - Handles user wishlist operations
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Api/WishlistController.php
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/wishlist",
     *     summary="Get user's wishlist",
     *     description="Retrieve all items in the authenticated user's wishlist",
     *     operationId="getWishlist",
     *     tags={"Wishlist"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="wishlist_items", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="product", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="iPhone 15"),
     *                         @OA\Property(property="price", type="number", format="float", example=999.99),
     *                         @OA\Property(property="sale_price", type="number", format="float", example=899.99),
     *                         @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg"),
     *                         @OA\Property(property="stock_quantity", type="integer", example=50),
     *                         @OA\Property(property="is_in_stock", type="boolean", example=true)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total_items", type="integer", example=10),
     *                 @OA\Property(property="total_value", type="number", format="float", example=2999.90)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get wishlist items with product details
            $wishlistItems = Wishlist::with(['product.category'])
                                   ->forUser($user->id)
                                   ->withActiveProducts()
                                   ->orderBy('created_at', 'desc')
                                   ->get();

            // Remove items with inactive products
            $unavailableItems = $wishlistItems->filter(function ($item) {
                return !$item->isProductAvailable();
            });

            if ($unavailableItems->isNotEmpty()) {
                foreach ($unavailableItems as $item) {
                    $item->delete();
                }
                
                $wishlistItems = $wishlistItems->filter(function ($item) {
                    return $item->isProductAvailable();
                });
            }

            return response()->json([
                'data' => $wishlistItems->values(),
                'meta' => [
                    'total' => $wishlistItems->count(),
                    'unavailable_items_removed' => $unavailableItems->count()
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch wishlist',
                'error' => 'Something went wrong while fetching wishlist items'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/wishlist",
     *     summary="Add product to wishlist",
     *     description="Add a product to the user's wishlist",
     *     operationId="addToWishlist",
     *     tags={"Wishlist"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product added to wishlist successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product added to wishlist successfully"),
     *             @OA\Property(property="wishlist_item", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=409, description="Product already in wishlist")
     * )
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();
            $productId = $request->product_id;

            // Check if product exists and is active
            $product = Product::active()->find($productId);
            
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found or unavailable'
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if product is already in wishlist
            if (Wishlist::isInWishlist($user->id, $productId)) {
                return response()->json([
                    'message' => 'Product is already in your wishlist'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Add to wishlist
            $wishlistItem = Wishlist::addToWishlist($user->id, $productId);
            $wishlistItem->load(['product.category']);

            return response()->json([
                'message' => 'Product added to wishlist successfully',
                'wishlist_item' => $wishlistItem
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add product to wishlist',
                'error' => 'Something went wrong while adding product to wishlist'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/wishlist/{id}",
     *     summary="Remove item from wishlist",
     *     description="Remove a specific product from user's wishlist by wishlist item ID",
     *     operationId="removeFromWishlist",
     *     tags={"Wishlist"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Wishlist item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product removed from wishlist successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product removed from wishlist successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Wishlist item not found")
     * )
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Find wishlist item
            $wishlistItem = Wishlist::where('id', $id)
                                  ->where('user_id', $user->id)
                                  ->first();

            if (!$wishlistItem) {
                return response()->json([
                    'message' => 'Wishlist item not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $wishlistItem->delete();

            return response()->json([
                'message' => 'Product removed from wishlist successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove product from wishlist',
                'error' => 'Something went wrong while removing product from wishlist'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/wishlist/product/{productId}",
     *     summary="Remove product from wishlist by product ID",
     *     description="Remove a product from wishlist using product ID",
     *     operationId="removeProductFromWishlist",
     *     tags={"Wishlist"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product removed from wishlist successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product removed from wishlist successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Product not found in wishlist")
     * )
     */
    public function removeByProduct(Request $request, $productId)
    {
        try {
            $user = $request->user();

            $removed = Wishlist::removeFromWishlist($user->id, $productId);

            if (!$removed) {
                return response()->json([
                    'message' => 'Product not found in wishlist'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'message' => 'Product removed from wishlist successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove product from wishlist',
                'error' => 'Something went wrong while removing product from wishlist'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/wishlist/{id}/move-to-cart",
     *     summary="Move wishlist item to cart",
     *     description="Move a product from wishlist to cart with specified quantity",
     *     operationId="moveWishlistItemToCart",
     *     tags={"Wishlist"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Wishlist item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="quantity", type="integer", minimum=1, maximum=100, example=1, description="Quantity to add to cart")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product moved to cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product moved to cart successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Wishlist item not found"),
     *     @OA\Response(response=422, description="Product not available or validation error")
     * )
     */
    public function moveToCart(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();
            $quantity = $request->get('quantity', 1);

            // Find wishlist item
            $wishlistItem = Wishlist::with(['product'])
                                  ->where('id', $id)
                                  ->where('user_id', $user->id)
                                  ->first();

            if (!$wishlistItem) {
                return response()->json([
                    'message' => 'Wishlist item not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Try to move to cart
            if ($wishlistItem->moveToCart($quantity)) {
                return response()->json([
                    'message' => 'Product moved to cart successfully'
                ], Response::HTTP_OK);
            } else {
                return response()->json([
                    'message' => 'Product is not available or out of stock'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to move product to cart',
                'error' => 'Something went wrong while moving product to cart'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/wishlist",
     *     summary="Clear entire wishlist",
     *     description="Remove all items from user's wishlist",
     *     operationId="clearWishlist",
     *     tags={"Wishlist"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Wishlist cleared successfully"),
     *             @OA\Property(property="items_removed", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function clear(Request $request)
    {
        try {
            $user = $request->user();

            $deletedCount = Wishlist::where('user_id', $user->id)->delete();

            return response()->json([
                'message' => 'Wishlist cleared successfully',
                'items_removed' => $deletedCount
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear wishlist',
                'error' => 'Something went wrong while clearing wishlist'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/wishlist/check/{productId}",
     *     summary="Check if product is in wishlist",
     *     description="Check whether a specific product is in user's wishlist",
     *     operationId="checkWishlistStatus",
     *     tags={"Wishlist"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Product ID to check",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Wishlist status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="in_wishlist", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function check(Request $request, $productId)
    {
        try {
            $user = $request->user();
            
            $isInWishlist = Wishlist::isInWishlist($user->id, $productId);

            return response()->json([
                'in_wishlist' => $isInWishlist
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check wishlist status',
                'error' => 'Something went wrong while checking wishlist'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}