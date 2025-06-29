<?php

/**
 * Order Controller - Handles order creation and management
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Api/OrderController.php
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CartItem;
use App\Mail\OrderConfirmation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/orders",
     *     summary="Create new order",
     *     description="Create a new order from user's cart items",
     *     operationId="createOrder",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"shipping_address", "payment_method"},
     *             @OA\Property(property="shipping_address", type="object",
     *                 required={"name", "address_line_1", "city", "state", "postal_code", "country"},
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="address_line_1", type="string", example="123 Main St"),
     *                 @OA\Property(property="address_line_2", type="string", example="Apt 4B"),
     *                 @OA\Property(property="city", type="string", example="New York"),
     *                 @OA\Property(property="state", type="string", example="NY"),
     *                 @OA\Property(property="postal_code", type="string", example="10001"),
     *                 @OA\Property(property="country", type="string", example="USA")
     *             ),
     *             @OA\Property(property="payment_method", type="string", enum={"credit_card", "paypal", "bank_transfer"}, example="credit_card"),
     *             @OA\Property(property="notes", type="string", example="Leave at front door")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Order created successfully"),
     *             @OA\Property(property="order", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="ORD-2023-001"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=299.98),
     *                 @OA\Property(property="shipping_address", type="object"),
     *                 @OA\Property(property="payment_method", type="string", example="credit_card"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="Empty cart or other error")
     * )
     */
    public function store(Request $request)
    {
        // Validate shipping address and order data
        $validator = Validator::make($request->all(), [
            'shipping_address' => ['required', 'array'],
            'shipping_address.name' => ['required', 'string', 'max:255'],
            'shipping_address.address_line_1' => ['required', 'string', 'max:255'],
            'shipping_address.address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.state' => ['required', 'string', 'max:100'],
            'shipping_address.postal_code' => ['required', 'string', 'max:20'],
            'shipping_address.country' => ['required', 'string', 'max:100'],
            'shipping_address.phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();

            // Get cart items
            $cartItems = CartItem::with(['product'])
                               ->forUser($user->id)
                               ->withActiveProducts()
                               ->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'message' => 'Cart is empty. Cannot create order.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Validate cart items availability and stock
            $unavailableItems = [];
            $validItems = [];
            $totalAmount = 0;

            foreach ($cartItems as $cartItem) {
                if (!$cartItem->isAvailable()) {
                    $unavailableItems[] = [
                        'product_name' => $cartItem->product ? $cartItem->product->name : 'Unknown Product',
                        'reason' => 'Product no longer available'
                    ];
                } elseif (!$cartItem->product->isInStock($cartItem->quantity)) {
                    $unavailableItems[] = [
                        'product_name' => $cartItem->product->name,
                        'requested_quantity' => $cartItem->quantity,
                        'available_quantity' => $cartItem->product->stock_quantity,
                        'reason' => 'Insufficient stock'
                    ];
                } else {
                    $validItems[] = $cartItem;
                    $totalAmount += $cartItem->quantity * $cartItem->product->effective_price;
                }
            }

            if (!empty($unavailableItems)) {
                return response()->json([
                    'message' => 'Some items in your cart are no longer available',
                    'unavailable_items' => $unavailableItems
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Use database transaction for order creation
            return DB::transaction(function () use ($user, $validItems, $totalAmount, $request) {
                
                // Create order
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => Order::STATUS_PENDING,
                    'total_amount' => $totalAmount,
                    'shipping_address' => $request->shipping_address,
                    'notes' => $request->notes,
                ]);

                // Create order items and reduce stock
                foreach ($validItems as $cartItem) {
                    // Create order item
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->product->effective_price,
                        'total' => $cartItem->quantity * $cartItem->product->effective_price,
                    ]);

                    // Reduce product stock
                    $cartItem->product->reduceStock($cartItem->quantity);
                }

                // Clear user's cart
                CartItem::where('user_id', $user->id)->delete();

                // Load order with relationships
                $order->load(['orderItems.product.category', 'user']);

                // Send order confirmation email
                try {
                    Mail::to($user->email)->send(new OrderConfirmation($order));
                } catch (\Exception $e) {
                    // Log email failure but don't fail the order creation
                    \Log::warning('Failed to send order confirmation email', [
                        'order_id' => $order->id,
                        'user_email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }

                return response()->json([
                    'message' => 'Order created successfully',
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'status_label' => $order->status_label,
                        'total_amount' => $order->total_amount,
                        'items_count' => $order->items_count,
                        'shipping_address' => $order->shipping_address,
                        'notes' => $order->notes,
                        'created_at' => $order->created_at,
                        'items' => $order->orderItems,
                    ]
                ], Response::HTTP_CREATED);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create order',
                'error' => 'Something went wrong while creating the order'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders",
     *     summary="Get user's order history",
     *     description="Retrieve authenticated user's order history with filtering options",
     *     operationId="getUserOrders",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "processing", "shipped", "delivered", "cancelled"}, example="pending")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="orders", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="order_number", type="string", example="ORD-2023-001"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=299.98),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="shipping_address", type="object"),
     *                     @OA\Property(property="payment_method", type="string", example="credit_card"),
     *                     @OA\Property(property="items_count", type="integer", example=3)
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=35)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request)
    {
        // Validate query parameters
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'in:pending,processing,shipped,delivered,cancelled'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();

            // Build query
            $query = Order::with(['orderItems.product'])
                         ->byUser($user->id);

            // Apply status filter
            if ($request->has('status')) {
                $query->status($request->status);
            }

            // Order by creation date (newest first)
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $orders = $query->paginate($perPage);

            return response()->json([
                'data' => $orders->items(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ],
                'filters_applied' => [
                    'status' => $request->status,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch orders',
                'error' => 'Something went wrong while fetching order history'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{orderNumber}",
     *     summary="Get order details",
     *     description="Retrieve detailed information about a specific order",
     *     operationId="getOrder",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="orderNumber",
     *         in="path",
     *         description="Order number",
     *         required=true,
     *         @OA\Schema(type="string", example="ORD-2023-001")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="ORD-2023-001"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="status_label", type="string", example="Pending"),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=299.98),
     *                 @OA\Property(property="items_count", type="integer", example=3),
     *                 @OA\Property(property="can_be_cancelled", type="boolean", example=true),
     *                 @OA\Property(property="shipping_address", type="object"),
     *                 @OA\Property(property="formatted_shipping_address", type="string"),
     *                 @OA\Property(property="notes", type="string", example="Leave at front door"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="items", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="quantity", type="integer", example=2),
     *                         @OA\Property(property="price", type="number", format="float", example=99.99),
     *                         @OA\Property(property="total", type="number", format="float", example=199.98),
     *                         @OA\Property(property="product", type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="iPhone 15"),
     *                             @OA\Property(property="slug", type="string", example="iphone-15")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function show($orderNumber, Request $request)
    {
        try {
            $user = $request->user();

            // Find order by order number and user
            $order = Order::with(['orderItems.product.category', 'user'])
                         ->where('order_number', $orderNumber)
                         ->where('user_id', $user->id)
                         ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'total_amount' => $order->total_amount,
                    'items_count' => $order->items_count,
                    'can_be_cancelled' => $order->can_be_cancelled,
                    'shipping_address' => $order->shipping_address,
                    'formatted_shipping_address' => $order->getFormattedShippingAddress(),
                    'notes' => $order->notes,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                    'items' => $order->orderItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'total' => $item->total,
                            'product' => [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'slug' => $item->product->slug,
                                'images' => $item->product->images,
                                'category' => $item->product->category,
                            ]
                        ];
                    })
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch order',
                'error' => 'Something went wrong while fetching order details'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/orders/{orderNumber}/cancel",
     *     summary="Cancel an order",
     *     description="Cancel a pending order and restore product stock",
     *     operationId="cancelOrder",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="orderNumber",
     *         in="path",
     *         description="Order number to cancel",
     *         required=true,
     *         @OA\Schema(type="string", example="ORD-2023-001")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Order cancelled successfully"),
     *             @OA\Property(property="order", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="ORD-2023-001"),
     *                 @OA\Property(property="status", type="string", example="cancelled"),
     *                 @OA\Property(property="status_label", type="string", example="Cancelled")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=422, description="Order cannot be cancelled")
     * )
     */
    public function cancel($orderNumber, Request $request)
    {
        try {
            $user = $request->user();

            // Find order
            $order = Order::with(['orderItems.product'])
                         ->where('order_number', $orderNumber)
                         ->where('user_id', $user->id)
                         ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$order->can_be_cancelled) {
                return response()->json([
                    'message' => 'Order cannot be cancelled',
                    'reason' => 'Order status does not allow cancellation'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Cancel order in transaction
            DB::transaction(function () use ($order) {
                $order->cancel(); // This also restores stock
            });

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->fresh()->status,
                    'status_label' => $order->fresh()->status_label,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel order',
                'error' => 'Something went wrong while cancelling the order'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/stats",
     *     summary="Get user order statistics",
     *     description="Retrieve order statistics for the authenticated user",
     *     operationId="getUserOrderStats",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Order statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_orders", type="integer", example=15),
     *                 @OA\Property(property="pending_orders", type="integer", example=2),
     *                 @OA\Property(property="processing_orders", type="integer", example=1),
     *                 @OA\Property(property="delivered_orders", type="integer", example=10),
     *                 @OA\Property(property="cancelled_orders", type="integer", example=2),
     *                 @OA\Property(property="total_spent", type="number", format="float", example=2499.85),
     *                 @OA\Property(property="recent_orders", type="integer", example=3, description="Orders in last 30 days")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();

            $stats = [
                'total_orders' => Order::byUser($user->id)->count(),
                'pending_orders' => Order::byUser($user->id)->status(Order::STATUS_PENDING)->count(),
                'processing_orders' => Order::byUser($user->id)->status(Order::STATUS_PROCESSING)->count(),
                'delivered_orders' => Order::byUser($user->id)->status(Order::STATUS_DELIVERED)->count(),
                'cancelled_orders' => Order::byUser($user->id)->status(Order::STATUS_CANCELLED)->count(),
                'total_spent' => Order::byUser($user->id)
                                   ->whereNotIn('status', [Order::STATUS_CANCELLED])
                                   ->sum('total_amount'),
                'recent_orders' => Order::byUser($user->id)
                                       ->recent(30)
                                       ->count(),
            ];

            return response()->json([
                'data' => $stats
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch order statistics',
                'error' => 'Something went wrong while fetching statistics'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}