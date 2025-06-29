<?php

/**
 * Admin Order Controller - Handles order management for administrators
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Admin/OrderController.php
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/orders",
     *     summary="Get all orders for admin",
     *     description="Retrieve all orders with filtering, searching, and pagination for admin management",
     *     operationId="getAdminOrders",
     *     tags={"Admin - Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "processing", "shipped", "delivered", "cancelled"}, example="pending")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by order number, user name, or email",
     *         required=false,
     *         @OA\Schema(type="string", example="ORD-2023")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter orders from date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2023-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter orders to date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2023-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="order_number", type="string", example="ORD-2023-001"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=299.98),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="user", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="statistics", type="object",
     *                 @OA\Property(property="total_orders", type="integer", example=150),
     *                 @OA\Property(property="total_revenue", type="number", format="float", example=15000.50)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required")
     * )
     */
    public function index(Request $request)
    {
        // Validate query parameters
        $validator = Validator::make($request->all(), [
            'status' => ['sometimes', 'in:pending,processing,shipped,delivered,cancelled'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'numeric', 'min:0'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'in:created_at,total_amount,order_number'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Build query with relationships
            $query = Order::with(['user:id,name,email', 'orderItems.product:id,name'])
                         ->withCount('orderItems');

            // Apply status filter
            if ($request->has('status')) {
                $query->status($request->status);
            }

            // Apply user filter
            if ($request->has('user_id')) {
                $query->byUser($request->user_id);
            }

            // Apply date range filter
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
            }
            if ($request->has('date_to')) {
                $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
            }

            // Apply amount range filter
            if ($request->has('min_amount')) {
                $query->where('total_amount', '>=', $request->min_amount);
            }
            if ($request->has('max_amount')) {
                $query->where('total_amount', '<=', $request->max_amount);
            }

            // Apply search filter (order number, user name, or email)
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'LIKE', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('name', 'LIKE', "%{$search}%")
                                   ->orWhere('email', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate results
            $perPage = $request->get('per_page', 20);
            $orders = $query->paginate($perPage);

            // Calculate statistics
            $stats = $this->calculateStatistics($request);

            return response()->json([
                'data' => $orders->items(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ],
                'statistics' => $stats,
                'filters_applied' => [
                    'status' => $request->status,
                    'user_id' => $request->user_id,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'min_amount' => $request->min_amount,
                    'max_amount' => $request->max_amount,
                    'search' => $request->search,
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch orders',
                'error' => 'Something went wrong while fetching orders'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/orders/{orderNumber}",
     *     summary="Get single order for admin",
     *     description="Get detailed order information for admin management",
     *     operationId="getAdminOrder",
     *     tags={"Admin - Orders"},
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
     *         description="Order retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="ORD-2023-001"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=299.98),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 ),
     *                 @OA\Property(property="items", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="quantity", type="integer", example=2),
     *                         @OA\Property(property="price", type="number", format="float", example=99.99),
     *                         @OA\Property(property="product", type="object")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function show($orderNumber)
    {
        try {
            $order = Order::with([
                'user:id,name,email,created_at',
                'orderItems.product.category'
            ])->where('order_number', $orderNumber)->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Add additional order information
            $orderData = [
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
                'user' => $order->user,
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
                            'sku' => $item->product->sku,
                            'images' => $item->product->images,
                            'category' => $item->product->category,
                        ]
                    ];
                })
            ];

            return response()->json([
                'data' => $orderData
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch order',
                'error' => 'Something went wrong while fetching order details'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/orders/{orderNumber}",
     *     summary="Update order status",
     *     description="Update order status and add admin notes",
     *     operationId="updateAdminOrder",
     *     tags={"Admin - Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="orderNumber",
     *         in="path",
     *         description="Order number to update",
     *         required=true,
     *         @OA\Schema(type="string", example="ORD-2023-001")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"pending", "processing", "shipped", "delivered", "cancelled"}, example="processing"),
     *             @OA\Property(property="admin_notes", type="string", maxLength=1000, example="Order processed and ready for shipping")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Order status updated successfully"),
     *             @OA\Property(property="order", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_number", type="string", example="ORD-2023-001"),
     *                 @OA\Property(property="status", type="string", example="processing"),
     *                 @OA\Property(property="previous_status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=422, description="Invalid status transition")
     * )
     */
    public function update($orderNumber, Request $request)
    {
        // Validate update data
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $order = Order::with(['user', 'orderItems.product'])
                         ->where('order_number', $orderNumber)
                         ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Order not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $newStatus = $request->status;
            $oldStatus = $order->status;

            // Validate status transition
            if (!$this->isValidStatusTransition($oldStatus, $newStatus)) {
                return response()->json([
                    'message' => 'Invalid status transition',
                    'current_status' => $oldStatus,
                    'requested_status' => $newStatus
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Handle status-specific logic
            DB::transaction(function () use ($order, $newStatus, $request) {
                // If cancelling order, restore stock
                if ($newStatus === Order::STATUS_CANCELLED && $order->status !== Order::STATUS_CANCELLED) {
                    foreach ($order->orderItems as $item) {
                        $item->product->increaseStock($item->quantity);
                    }
                }

                // Update order status
                $order->update([
                    'status' => $newStatus,
                    'admin_notes' => $request->admin_notes,
                ]);
            });

            // TODO: Send status update email to customer
            // Mail::to($order->user->email)->send(new OrderStatusUpdate($order));

            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->fresh()->status,
                    'status_label' => $order->fresh()->status_label,
                    'previous_status' => $oldStatus,
                    'admin_notes' => $order->fresh()->admin_notes,
                    'updated_at' => $order->fresh()->updated_at,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update order status',
                'error' => 'Something went wrong while updating order status'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/orders/stats",
     *     summary="Get order statistics",
     *     description="Retrieve comprehensive order statistics for admin dashboard",
     *     operationId="getOrderStats",
     *     tags={"Admin - Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period for statistics",
     *         required=false,
     *         @OA\Schema(type="string", enum={"today", "week", "month", "quarter", "year"}, example="month")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="totals", type="object",
     *                     @OA\Property(property="orders", type="integer", example=150),
     *                     @OA\Property(property="revenue", type="number", format="float", example=15000.50),
     *                     @OA\Property(property="customers", type="integer", example=75),
     *                     @OA\Property(property="average_order_value", type="number", format="float", example=100.00)
     *                 ),
     *                 @OA\Property(property="by_status", type="object",
     *                     @OA\Property(property="pending", type="integer", example=10),
     *                     @OA\Property(property="processing", type="integer", example=25),
     *                     @OA\Property(property="shipped", type="integer", example=20),
     *                     @OA\Property(property="delivered", type="integer", example=85),
     *                     @OA\Property(property="cancelled", type="integer", example=10)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required")
     * )
     */
    public function stats(Request $request)
    {
        // Validate query parameters
        $validator = Validator::make($request->all(), [
            'period' => ['sometimes', 'in:today,week,month,quarter,year'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $period = $request->get('period', 'month');
            $stats = $this->calculateDetailedStatistics($period, $request);

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

    /**
     * @OA\Post(
     *     path="/api/admin/orders/bulk-update",
     *     summary="Bulk update order statuses",
     *     description="Update status for multiple orders at once",
     *     operationId="bulkUpdateOrders",
     *     tags={"Admin - Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_ids", "status"},
     *             @OA\Property(property="order_ids", type="array", minItems=1, @OA\Items(type="integer", example=1)),
     *             @OA\Property(property="status", type="string", enum={"processing", "shipped", "delivered"}, example="processing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk update completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bulk status update completed successfully"),
     *             @OA\Property(property="updated_count", type="integer", example=5),
     *             @OA\Property(property="new_status", type="string", example="processing")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=422, description="Validation error or invalid status transitions")
     * )
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'status' => ['required', 'in:processing,shipped,delivered'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $orderIds = $request->order_ids;
            $newStatus = $request->status;

            // Get orders and validate transitions
            $orders = Order::whereIn('id', $orderIds)->get();
            $invalidOrders = [];

            foreach ($orders as $order) {
                if (!$this->isValidStatusTransition($order->status, $newStatus)) {
                    $invalidOrders[] = [
                        'order_number' => $order->order_number,
                        'current_status' => $order->status,
                        'reason' => 'Invalid status transition'
                    ];
                }
            }

            if (!empty($invalidOrders)) {
                return response()->json([
                    'message' => 'Some orders cannot be updated',
                    'invalid_orders' => $invalidOrders
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Update valid orders
            $updatedCount = Order::whereIn('id', $orderIds)->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);

            return response()->json([
                'message' => "Bulk status update completed successfully",
                'updated_count' => $updatedCount,
                'new_status' => $newStatus
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to perform bulk update',
                'error' => 'Something went wrong during bulk update'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calculate basic statistics for the index page
     */
    private function calculateStatistics($request)
    {
        $query = Order::query();

        // Apply same filters as main query
        if ($request->has('status')) {
            $query->status($request->status);
        }
        if ($request->has('user_id')) {
            $query->byUser($request->user_id);
        }
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        return [
            'total_orders' => (clone $query)->count(),
            'total_revenue' => (clone $query)->whereNotIn('status', [Order::STATUS_CANCELLED])->sum('total_amount'),
            'pending_orders' => (clone $query)->status(Order::STATUS_PENDING)->count(),
            'processing_orders' => (clone $query)->status(Order::STATUS_PROCESSING)->count(),
            'shipped_orders' => (clone $query)->status(Order::STATUS_SHIPPED)->count(),
            'delivered_orders' => (clone $query)->status(Order::STATUS_DELIVERED)->count(),
            'cancelled_orders' => (clone $query)->status(Order::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * Calculate detailed statistics for the stats endpoint
     */
    private function calculateDetailedStatistics($period, $request)
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        // Set date range based on period
        if (!$dateFrom || !$dateTo) {
            switch ($period) {
                case 'today':
                    $dateFrom = Carbon::today();
                    $dateTo = Carbon::today()->endOfDay();
                    break;
                case 'week':
                    $dateFrom = Carbon::now()->startOfWeek();
                    $dateTo = Carbon::now()->endOfWeek();
                    break;
                case 'month':
                    $dateFrom = Carbon::now()->startOfMonth();
                    $dateTo = Carbon::now()->endOfMonth();
                    break;
                case 'quarter':
                    $dateFrom = Carbon::now()->startOfQuarter();
                    $dateTo = Carbon::now()->endOfQuarter();
                    break;
                case 'year':
                    $dateFrom = Carbon::now()->startOfYear();
                    $dateTo = Carbon::now()->endOfYear();
                    break;
            }
        }

        $query = Order::whereBetween('created_at', [$dateFrom, $dateTo]);

        return [
            'period' => $period,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'totals' => [
                'orders' => (clone $query)->count(),
                'revenue' => (clone $query)->whereNotIn('status', [Order::STATUS_CANCELLED])->sum('total_amount'),
                'cancelled_revenue' => (clone $query)->where('status', Order::STATUS_CANCELLED)->sum('total_amount'),
                'customers' => (clone $query)->distinct('user_id')->count('user_id'),
                'average_order_value' => (clone $query)->whereNotIn('status', [Order::STATUS_CANCELLED])->avg('total_amount'),
            ],
            'by_status' => [
                'pending' => (clone $query)->status(Order::STATUS_PENDING)->count(),
                'processing' => (clone $query)->status(Order::STATUS_PROCESSING)->count(),
                'shipped' => (clone $query)->status(Order::STATUS_SHIPPED)->count(),
                'delivered' => (clone $query)->status(Order::STATUS_DELIVERED)->count(),
                'cancelled' => (clone $query)->status(Order::STATUS_CANCELLED)->count(),
            ]
        ];
    }

    /**
     * Check if status transition is valid
     */
    private function isValidStatusTransition($currentStatus, $newStatus)
    {
        $validTransitions = [
            Order::STATUS_PENDING => [Order::STATUS_PROCESSING, Order::STATUS_CANCELLED],
            Order::STATUS_PROCESSING => [Order::STATUS_SHIPPED, Order::STATUS_CANCELLED],
            Order::STATUS_SHIPPED => [Order::STATUS_DELIVERED],
            Order::STATUS_DELIVERED => [], // Final state
            Order::STATUS_CANCELLED => [], // Final state
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
}