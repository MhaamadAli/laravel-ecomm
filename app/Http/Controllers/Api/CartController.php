<?php

/**
 * Cart Controller - Handles shopping cart operations
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Api/CartController.php
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/cart",
     *     summary="Get user's cart items",
     *     description="Retrieve all items in the authenticated user's shopping cart",
     *     operationId="getCart",
     *     tags={"Shopping Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Cart items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="cart_items", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2),
     *                     @OA\Property(property="unit_price", type="number", format="float", example=99.99),
     *                     @OA\Property(property="total_price", type="number", format="float", example=199.98),
     *                     @OA\Property(property="product", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="iPhone 15"),
     *                         @OA\Property(property="price", type="number", format="float", example=99.99),
     *                         @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total_items", type="integer", example=3),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=299.97)
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
            
            // Get cart items with product details
            $cartItems = CartItem::with(['product.category'])
                               ->forUser($user->id)
                               ->withActiveProducts()
                               ->get();

            // Remove items with inactive or out-of-stock products
            $unavailableItems = $cartItems->filter(function ($item) {
                return !$item->isAvailable();
            });

            if ($unavailableItems->isNotEmpty()) {
                // Remove unavailable items
                foreach ($unavailableItems as $item) {
                    $item->delete();
                }
                
                // Refresh cart items
                $cartItems = $cartItems->filter(function ($item) {
                    return $item->isAvailable();
                });
            }

            // Calculate totals
            $subtotal = $cartItems->sum('subtotal');
            $itemCount = $cartItems->sum('quantity');

            return response()->json([
                'items' => $cartItems->values(),
                'totals' => [
                    'subtotal' => $subtotal,
                    'total' => $subtotal, // Add tax/shipping calculations here if needed
                    'item_count' => $itemCount,
                    'items_total' => $cartItems->count()
                ],
                'meta' => [
                    'currency' => 'USD', // Make this configurable
                    'unavailable_items_removed' => $unavailableItems->count()
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch cart',
                'error' => 'Something went wrong while fetching cart items'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/cart",
     *     summary="Add item to cart",
     *     description="Add a product to the user's shopping cart",
     *     operationId="addToCart",
     *     tags={"Shopping Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id", "quantity"},
     *             @OA\Property(property="product_id", type="integer", example=1),
     *             @OA\Property(property="quantity", type="integer", example=2, minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item added to cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Item added to cart successfully"),
     *             @OA\Property(property="cart_item", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=2),
     *                 @OA\Property(property="unit_price", type="number", format="float", example=99.99),
     *                 @OA\Property(property="total_price", type="number", format="float", example=199.98)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
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
            $quantity = $request->quantity;

            // Check if product exists and is active
            $product = Product::active()->find($productId);
            
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found or unavailable'
                ], Response::HTTP_NOT_FOUND);
            }

            // Check stock availability
            if (!$product->isInStock($quantity)) {
                return response()->json([
                    'message' => 'Insufficient stock available',
                    'available_quantity' => $product->stock_quantity
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check if item already exists in cart
            $existingCartItem = CartItem::where('user_id', $user->id)
                                      ->where('product_id', $productId)
                                      ->first();

            if ($existingCartItem) {
                // Update existing cart item
                $newQuantity = $existingCartItem->quantity + $quantity;
                
                if (!$product->isInStock($newQuantity)) {
                    return response()->json([
                        'message' => 'Cannot add more items. Insufficient stock available.',
                        'current_in_cart' => $existingCartItem->quantity,
                        'available_quantity' => $product->stock_quantity
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $existingCartItem->updateQuantity($newQuantity);
                $cartItem = $existingCartItem->fresh(['product.category']);

                return response()->json([
                    'message' => 'Cart item updated successfully',
                    'cart_item' => $cartItem,
                    'action' => 'updated'
                ], Response::HTTP_OK);
            } else {
                // Create new cart item
                $cartItem = CartItem::create([
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);

                $cartItem->load(['product.category']);

                return response()->json([
                    'message' => 'Item added to cart successfully',
                    'cart_item' => $cartItem,
                    'action' => 'added'
                ], Response::HTTP_CREATED);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add item to cart',
                'error' => 'Something went wrong while adding item to cart'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/cart/{id}",
     *     summary="Update cart item quantity",
     *     description="Update the quantity of a specific item in the cart",
     *     operationId="updateCartItem",
     *     tags={"Shopping Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Cart item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity"},
     *             @OA\Property(property="quantity", type="integer", example=3, minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart item updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cart item updated successfully"),
     *             @OA\Property(property="cart_item", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="quantity", type="integer", example=3),
     *                 @OA\Property(property="total_price", type="number", format="float", example=299.97)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Cart item not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();
            $quantity = $request->quantity;

            // Find cart item
            $cartItem = CartItem::with(['product'])
                              ->where('id', $id)
                              ->where('user_id', $user->id)
                              ->first();

            if (!$cartItem) {
                return response()->json([
                    'message' => 'Cart item not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if product is still available
            if (!$cartItem->product || !$cartItem->product->is_active) {
                // Remove unavailable item
                $cartItem->delete();
                
                return response()->json([
                    'message' => 'Product is no longer available. Item removed from cart.'
                ], Response::HTTP_GONE);
            }

            // Check stock availability
            if (!$cartItem->product->isInStock($quantity)) {
                return response()->json([
                    'message' => 'Insufficient stock available',
                    'available_quantity' => $cartItem->product->stock_quantity
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Update quantity
            $cartItem->updateQuantity($quantity);
            $cartItem->load(['product.category']);

            return response()->json([
                'message' => 'Cart item updated successfully',
                'cart_item' => $cartItem
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update cart item',
                'error' => 'Something went wrong while updating cart item'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/cart/{id}",
     *     summary="Remove item from cart",
     *     description="Remove a specific item from the shopping cart",
     *     operationId="removeCartItem",
     *     tags={"Shopping Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Cart item ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item removed from cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Item removed from cart successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Cart item not found")
     * )
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Find and delete cart item
            $cartItem = CartItem::where('id', $id)
                              ->where('user_id', $user->id)
                              ->first();

            if (!$cartItem) {
                return response()->json([
                    'message' => 'Cart item not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $cartItem->delete();

            return response()->json([
                'message' => 'Item removed from cart successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove cart item',
                'error' => 'Something went wrong while removing cart item'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/cart",
     *     summary="Clear entire cart",
     *     description="Remove all items from the user's shopping cart",
     *     operationId="clearCart",
     *     tags={"Shopping Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Cart cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cart cleared successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function clear(Request $request)
    {
        try {
            $user = $request->user();

            // Delete all cart items for user
            $deletedCount = CartItem::where('user_id', $user->id)->delete();

            return response()->json([
                'message' => 'Cart cleared successfully',
                'items_removed' => $deletedCount
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear cart',
                'error' => 'Something went wrong while clearing cart'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/cart/summary",
     *     summary="Get cart summary",
     *     description="Get cart item count and total amount",
     *     operationId="getCartSummary",
     *     tags={"Shopping Cart"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Cart summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="cart_summary", type="object",
     *                 @OA\Property(property="total_items", type="integer", example=5),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=499.95),
     *                 @OA\Property(property="currency", type="string", example="USD")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function summary(Request $request)
    {
        try {
            $user = $request->user();

            $cartItems = CartItem::forUser($user->id)
                               ->withActiveProducts()
                               ->get();

            $itemCount = $cartItems->sum('quantity');
            $subtotal = $cartItems->sum('subtotal');

            return response()->json([
                'item_count' => $itemCount,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'items_total' => $cartItems->count()
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch cart summary',
                'error' => 'Something went wrong while fetching cart summary'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate cart before checkout
     * POST /api/cart/validate
     */
    public function validate(Request $request)
    {
        try {
            $user = $request->user();

            $cartItems = CartItem::with(['product'])
                               ->forUser($user->id)
                               ->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Cart is empty',
                    'errors' => ['cart' => 'Your cart is empty']
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $errors = [];
            $validItems = [];
            $invalidItems = [];

            foreach ($cartItems as $item) {
                if (!$item->isAvailable()) {
                    $invalidItems[] = [
                        'id' => $item->id,
                        'product_name' => $item->product ? $item->product->name : 'Unknown Product',
                        'reason' => 'Product no longer available'
                    ];
                    $item->delete(); // Remove invalid items
                } elseif (!$item->product->isInStock($item->quantity)) {
                    $invalidItems[] = [
                        'id' => $item->id,
                        'product_name' => $item->product->name,
                        'requested_quantity' => $item->quantity,
                        'available_quantity' => $item->product->stock_quantity,
                        'reason' => 'Insufficient stock'
                    ];
                } else {
                    $validItems[] = $item;
                }
            }

            $isValid = empty($invalidItems);

            return response()->json([
                'valid' => $isValid,
                'message' => $isValid ? 'Cart is valid for checkout' : 'Cart has validation errors',
                'valid_items' => $validItems,
                'invalid_items' => $invalidItems,
                'totals' => [
                    'subtotal' => collect($validItems)->sum('subtotal'),
                    'item_count' => collect($validItems)->sum('quantity')
                ]
            ], $isValid ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to validate cart',
                'error' => 'Something went wrong while validating cart'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}