<?php

/**
 * Admin User Controller - Handles user management for administrators
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Admin/UserController.php
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/users",
     *     summary="Get all users for admin",
     *     description="Retrieve all users with filtering, searching, and pagination for admin management",
     *     operationId="getAdminUsers",
     *     tags={"Admin - Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Filter by user role",
     *         required=false,
     *         @OA\Schema(type="string", enum={"user", "admin", "all"}, example="user")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by verification status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "all"}, example="active")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or email",
     *         required=false,
     *         @OA\Schema(type="string", example="john")
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
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="role", type="string", example="user"),
     *                     @OA\Property(property="is_verified", type="boolean", example=true),
     *                     @OA\Property(property="orders_count", type="integer", example=5),
     *                     @OA\Property(property="total_spent", type="number", format="float", example=299.99),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="statistics", type="object",
     *                 @OA\Property(property="total_users", type="integer", example=150),
     *                 @OA\Property(property="verified_users", type="integer", example=120)
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
            'role' => ['sometimes', 'in:user,admin,all'],
            'status' => ['sometimes', 'in:active,inactive,all'],
            'search' => ['sometimes', 'string', 'max:255'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['sometimes', 'in:name,email,created_at,orders_count,total_spent'],
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
            // Build query with user statistics
            $query = User::withCount(['orders', 'reviews', 'cartItems', 'wishlistItems'])
                        ->withSum('orders as total_spent', 'total_amount');

            // Apply role filter
            $role = $request->get('role', 'all');
            if ($role === 'admin') {
                $query->where('role', User::ROLE_ADMIN);
            } elseif ($role === 'user') {
                $query->where('role', User::ROLE_USER);
            }

            // Apply status filter
            $status = $request->get('status', 'all');
            if ($status === 'active') {
                $query->whereNotNull('email_verified_at');
            } elseif ($status === 'inactive') {
                $query->whereNull('email_verified_at');
            }

            // Apply date range filter
            if ($request->has('date_from')) {
                $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
            }
            if ($request->has('date_to')) {
                $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
            }

            // Apply search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate results
            $perPage = $request->get('per_page', 20);
            $users = $query->paginate($perPage);

            // Add additional data for each user
            $users->through(function ($user) {
                $user->makeVisible(['created_at', 'updated_at', 'email_verified_at']);
                $user->setAttribute('is_verified', !is_null($user->email_verified_at));
                $user->setAttribute('last_order_date', $user->orders()->latest()->value('created_at'));
                $user->setAttribute('account_age_days', $user->created_at->diffInDays(now()));
                return $user;
            });

            // Calculate statistics
            $stats = $this->calculateStatistics($request);

            return response()->json([
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
                'statistics' => $stats,
                'filters_applied' => [
                    'role' => $role,
                    'status' => $status,
                    'search' => $request->search,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch users',
                'error' => 'Something went wrong while fetching users'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/{id}",
     *     summary="Get single user for admin",
     *     description="Get detailed user information including order history and statistics",
     *     operationId="getAdminUser",
     *     tags={"Admin - Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="role", type="string", example="user"),
     *                 @OA\Property(property="is_verified", type="boolean", example=true),
     *                 @OA\Property(property="statistics", type="object",
     *                     @OA\Property(property="orders_count", type="integer", example=5),
     *                     @OA\Property(property="total_spent", type="number", format="float", example=299.99),
     *                     @OA\Property(property="account_age_days", type="integer", example=365)
     *                 ),
     *                 @OA\Property(property="recent_orders", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="recent_reviews", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show($id)
    {
        try {
            $user = User::with(['orders.orderItems.product', 'reviews.product'])
                       ->withCount(['orders', 'reviews', 'cartItems', 'wishlistItems'])
                       ->withSum('orders as total_spent', 'total_amount')
                       ->findOrFail($id);

            // Add additional user statistics
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => $user->role_label,
                'email_verified_at' => $user->email_verified_at,
                'is_verified' => !is_null($user->email_verified_at),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'statistics' => [
                    'orders_count' => $user->orders_count,
                    'reviews_count' => $user->reviews_count,
                    'cart_items_count' => $user->cart_items_count,
                    'wishlist_items_count' => $user->wishlist_items_count,
                    'total_spent' => $user->total_spent ?? 0,
                    'average_order_value' => $user->orders_count > 0 ? ($user->total_spent / $user->orders_count) : 0,
                    'account_age_days' => $user->created_at->diffInDays(now()),
                    'last_order_date' => $user->orders()->latest()->value('created_at'),
                    'last_review_date' => $user->reviews()->latest()->value('created_at'),
                ],
                'recent_orders' => $user->orders()
                                      ->with(['orderItems.product:id,name'])
                                      ->latest()
                                      ->take(5)
                                      ->get()
                                      ->map(function ($order) {
                                          return [
                                              'id' => $order->id,
                                              'order_number' => $order->order_number,
                                              'status' => $order->status,
                                              'status_label' => $order->status_label,
                                              'total_amount' => $order->total_amount,
                                              'items_count' => $order->items_count,
                                              'created_at' => $order->created_at,
                                          ];
                                      }),
                'recent_reviews' => $user->reviews()
                                        ->with(['product:id,name,slug'])
                                        ->latest()
                                        ->take(5)
                                        ->get()
                                        ->map(function ($review) {
                                            return [
                                                'id' => $review->id,
                                                'rating' => $review->rating,
                                                'title' => $review->title,
                                                'comment' => \Illuminate\Support\Str::limit($review->comment, 100),
                                                'is_approved' => $review->is_approved,
                                                'created_at' => $review->created_at,
                                                'product' => $review->product,
                                            ];
                                        }),
            ];

            return response()->json([
                'data' => $userData
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch user',
                'error' => 'Something went wrong while fetching user details'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/users/{id}",
     *     summary="Update user information",
     *     description="Update user details including role and verification status",
     *     operationId="updateAdminUser",
     *     tags={"Admin - Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="John Smith"),
     *             @OA\Property(property="email", type="string", format="email", example="johnsmith@example.com"),
     *             @OA\Property(property="role", type="string", enum={"user", "admin"}, example="user"),
     *             @OA\Property(property="password", type="string", minLength=8, example="newpassword123"),
     *             @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User updated successfully"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Smith"),
     *                 @OA\Property(property="email", type="string", example="johnsmith@example.com"),
     *                 @OA\Property(property="role", type="string", example="user")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=422, description="Validation error or self-demotion attempt")
     * )
     */
    public function update($id, Request $request)
    {
        try {
            $user = User::findOrFail($id);

            // Validate update data
            $validator = Validator::make($request->all(), [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $id],
                'role' => ['sometimes', 'required', 'in:user,admin'],
                'password' => ['sometimes', 'nullable', 'string', 'min:8'],
                'email_verified_at' => ['sometimes', 'nullable', 'date'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Prevent self-demotion for admin users
            $currentAdmin = $request->user();
            if ($currentAdmin->id == $id && $request->has('role') && $request->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'message' => 'You cannot change your own admin role'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Update user data
            $updateData = $request->only(['name', 'email', 'role', 'email_verified_at']);
            
            if ($request->has('password') && !empty($request->password)) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_label' => $user->role_label,
                    'email_verified_at' => $user->email_verified_at,
                    'updated_at' => $user->updated_at,
                ]
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user',
                'error' => 'Something went wrong while updating the user'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/users/{id}",
     *     summary="Delete or deactivate user",
     *     description="Delete user if no orders exist, otherwise deactivate account",
     *     operationId="deleteAdminUser",
     *     tags={"Admin - Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted or deactivated successfully",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="User deleted successfully"),
     *                     @OA\Property(property="action", type="string", example="deleted")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="User deactivated successfully (has existing orders)"),
     *                     @OA\Property(property="action", type="string", example="deactivated")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=422, description="Cannot delete own account")
     * )
     */
    public function destroy($id, Request $request)
    {
        try {
            $user = User::withCount('orders')->findOrFail($id);

            // Prevent self-deletion for admin users
            $currentAdmin = $request->user();
            if ($currentAdmin->id == $id) {
                return response()->json([
                    'message' => 'You cannot delete your own account'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check for existing orders
            if ($user->orders_count > 0) {
                // Deactivate instead of delete if user has orders
                $user->update(['email_verified_at' => null]);
                
                return response()->json([
                    'message' => 'User deactivated successfully (has existing orders)',
                    'action' => 'deactivated'
                ], Response::HTTP_OK);
            } else {
                // Safe to delete if no orders
                $user->delete();
                
                return response()->json([
                    'message' => 'User deleted successfully',
                    'action' => 'deleted'
                ], Response::HTTP_OK);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user',
                'error' => 'Something went wrong while deleting the user'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/users/stats",
     *     summary="Get user statistics",
     *     description="Retrieve comprehensive user statistics for admin dashboard",
     *     operationId="getUserStats",
     *     tags={"Admin - Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_users", type="integer", example=150),
     *                 @OA\Property(property="admin_users", type="integer", example=5),
     *                 @OA\Property(property="regular_users", type="integer", example=145),
     *                 @OA\Property(property="verified_users", type="integer", example=120),
     *                 @OA\Property(property="unverified_users", type="integer", example=30),
     *                 @OA\Property(property="users_with_orders", type="integer", example=75),
     *                 @OA\Property(property="users_without_orders", type="integer", example=75),
     *                 @OA\Property(property="new_users_this_month", type="integer", example=15),
     *                 @OA\Property(property="new_users_this_week", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required")
     * )
     */
    public function stats(Request $request)
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'admin_users' => User::where('role', User::ROLE_ADMIN)->count(),
                'regular_users' => User::where('role', User::ROLE_USER)->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'unverified_users' => User::whereNull('email_verified_at')->count(),
                'users_with_orders' => User::has('orders')->count(),
                'users_without_orders' => User::doesntHave('orders')->count(),
                'new_users_this_month' => User::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
                'new_users_this_week' => User::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            ];

            return response()->json([
                'data' => $stats
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch user statistics',
                'error' => 'Something went wrong while fetching statistics'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calculate statistics for the index page
     */
    private function calculateStatistics($request)
    {
        $query = User::query();

        // Apply same filters as main query
        $role = $request->get('role', 'all');
        if ($role === 'admin') {
            $query->where('role', User::ROLE_ADMIN);
        } elseif ($role === 'user') {
            $query->where('role', User::ROLE_USER);
        }

        $status = $request->get('status', 'all');
        if ($status === 'active') {
            $query->whereNotNull('email_verified_at');
        } elseif ($status === 'inactive') {
            $query->whereNull('email_verified_at');
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        return [
            'total_users' => (clone $query)->count(),
            'admin_users' => (clone $query)->where('role', User::ROLE_ADMIN)->count(),
            'regular_users' => (clone $query)->where('role', User::ROLE_USER)->count(),
            'verified_users' => (clone $query)->whereNotNull('email_verified_at')->count(),
            'unverified_users' => (clone $query)->whereNull('email_verified_at')->count(),
        ];
    }
}