<?php

/**
 * Admin Product Controller - Handles product management for administrators
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Admin/ProductController.php
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/products",
     *     summary="Get all products for admin",
     *     description="Retrieve all products with admin management options",
     *     operationId="getAdminProducts",
     *     tags={"Admin - Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by product status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "all"}, example="active")
     *     ),
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         description="Filter featured products",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         required=false,
     *         @OA\Schema(type="string", example="iPhone")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price", "stock_quantity", "created_at"}, example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
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
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="products", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="iPhone 15"),
     *                     @OA\Property(property="price", type="number", format="float", example=999.99),
     *                     @OA\Property(property="sale_price", type="number", format="float", example=899.99),
     *                     @OA\Property(property="stock_quantity", type="integer", example=50),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="featured", type="boolean", example=false),
     *                     @OA\Property(property="category", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Electronics")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=10),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=195)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request)
    {
        // Validate query parameters
        $validator = Validator::make($request->all(), [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', 'in:active,inactive,all'],
            'featured' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'in:name,price,stock_quantity,created_at'],
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
            // Build query
            $query = Product::with(['category', 'reviews']);

            // Apply status filter
            $status = $request->get('status', 'all');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }

            // Apply other filters
            if ($request->has('category_id')) {
                $query->byCategory($request->category_id);
            }

            if ($request->has('featured')) {
                $query->where('featured', $request->boolean('featured'));
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate results
            $perPage = $request->get('per_page', 20);
            $products = $query->paginate($perPage);

            // Add additional statistics for each product
            $products->through(function ($product) {
                $product->makeVisible(['created_at', 'updated_at']);
                $product->setAttribute('orders_count', $product->orderItems()->count());
                $product->setAttribute('cart_items_count', $product->cartItems()->count());
                return $product;
            });

            return response()->json([
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'filters_applied' => [
                    'category_id' => $request->category_id,
                    'status' => $status,
                    'featured' => $request->boolean('featured'),
                    'search' => $request->search,
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch products',
                'error' => 'Something went wrong while fetching products'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/products/{id}",
     *     summary="Get single product for admin",
     *     description="Get detailed product information for admin editing",
     *     operationId="getAdminProduct",
     *     tags={"Admin - Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="product", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="iPhone 15"),
     *                 @OA\Property(property="description", type="string", example="Latest iPhone with advanced features"),
     *                 @OA\Property(property="price", type="number", format="float", example=999.99),
     *                 @OA\Property(property="sale_price", type="number", format="float", example=899.99),
     *                 @OA\Property(property="stock_quantity", type="integer", example=50),
     *                 @OA\Property(property="sku", type="string", example="IPHONE15-001"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="featured", type="boolean", example=false),
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="category", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function show($id)
    {
        try {
            $product = Product::with(['category', 'reviews.user', 'orderItems', 'cartItems'])
                             ->findOrFail($id);

            // Add additional statistics
            $product->setAttribute('orders_count', $product->orderItems()->count());
            $product->setAttribute('cart_items_count', $product->cartItems()->count());
            $product->makeVisible(['created_at', 'updated_at']);

            return response()->json([
                'data' => $product
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch product',
                'error' => 'Something went wrong while fetching product details'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/products",
     *     summary="Create new product",
     *     description="Create a new product with all details",
     *     operationId="createProduct",
     *     tags={"Admin - Products"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "description", "price", "stock_quantity", "category_id"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="iPhone 15 Pro"),
     *             @OA\Property(property="description", type="string", example="Latest iPhone with advanced camera and performance"),
     *             @OA\Property(property="short_description", type="string", maxLength=500, example="Premium smartphone with titanium design"),
     *             @OA\Property(property="price", type="number", format="float", minimum=0, example=1199.99),
     *             @OA\Property(property="sale_price", type="number", format="float", minimum=0, example=1099.99),
     *             @OA\Property(property="sku", type="string", maxLength=50, example="IPHONE15PRO-001"),
     *             @OA\Property(property="stock_quantity", type="integer", minimum=0, example=100),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="images", type="array", maxItems=10, @OA\Items(type="string", example="https://example.com/image1.jpg")),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="featured", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product created successfully"),
     *             @OA\Property(property="product", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="iPhone 15 Pro"),
     *                 @OA\Property(property="slug", type="string", example="iphone-15-pro"),
     *                 @OA\Property(property="price", type="number", format="float", example=1199.99),
     *                 @OA\Property(property="stock_quantity", type="integer", example=100),
     *                 @OA\Property(property="category", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        // Validate product data
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['string', 'max:255'], // URLs for images
            'is_active' => ['boolean'],
            'featured' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Create product
            $product = Product::create([
                'name' => $request->name,
                'description' => $request->description,
                'short_description' => $request->short_description,
                'price' => $request->price,
                'sale_price' => $request->sale_price,
                'sku' => $request->sku,
                'stock_quantity' => $request->stock_quantity,
                'category_id' => $request->category_id,
                'images' => $request->images ?? [],
                'is_active' => $request->boolean('is_active', true),
                'featured' => $request->boolean('featured', false),
            ]);

            $product->load(['category']);

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => 'Something went wrong while creating the product'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/products/{id}",
     *     summary="Update existing product",
     *     description="Update an existing product with new details",
     *     operationId="updateProduct",
     *     tags={"Admin - Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="iPhone 15 Pro Max"),
     *             @OA\Property(property="description", type="string", example="Updated product description"),
     *             @OA\Property(property="short_description", type="string", maxLength=500, example="Updated short description"),
     *             @OA\Property(property="price", type="number", format="float", minimum=0, example=1299.99),
     *             @OA\Property(property="sale_price", type="number", format="float", minimum=0, example=1199.99),
     *             @OA\Property(property="sku", type="string", maxLength=50, example="IPHONE15PROMAX-001"),
     *             @OA\Property(property="stock_quantity", type="integer", minimum=0, example=75),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="images", type="array", maxItems=10, @OA\Items(type="string")),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="featured", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product updated successfully"),
     *             @OA\Property(property="product", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="iPhone 15 Pro Max"),
     *                 @OA\Property(property="price", type="number", format="float", example=1299.99)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update($id, Request $request)
    {
        try {
            $product = Product::findOrFail($id);

            // Validate update data
            $validator = Validator::make($request->all(), [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'description' => ['sometimes', 'required', 'string'],
                'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
                'price' => ['sometimes', 'required', 'numeric', 'min:0'],
                'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'sku' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:products,sku,' . $id],
                'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
                'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
                'images' => ['sometimes', 'nullable', 'array', 'max:10'],
                'images.*' => ['string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
                'featured' => ['sometimes', 'boolean'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Update product
            $product->update($request->only([
                'name', 'description', 'short_description', 'price', 'sale_price',
                'sku', 'stock_quantity', 'category_id', 'images', 'is_active', 'featured'
            ]));

            $product->load(['category']);

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => 'Something went wrong while updating the product'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/products/{id}",
     *     summary="Delete or deactivate product",
     *     description="Delete product if no orders exist, otherwise deactivate it",
     *     operationId="deleteProduct",
     *     tags={"Admin - Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted or deactivated successfully",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Product deleted successfully"),
     *                     @OA\Property(property="action", type="string", example="deleted")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Product deactivated successfully (has existing orders)"),
     *                     @OA\Property(property="action", type="string", example="deactivated")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Check for existing orders
            $hasOrders = $product->orderItems()->exists();

            if ($hasOrders) {
                // Deactivate instead of delete if product has orders
                $product->update(['is_active' => false]);
                
                return response()->json([
                    'message' => 'Product deactivated successfully (has existing orders)',
                    'action' => 'deactivated'
                ], Response::HTTP_OK);
            } else {
                // Safe to delete if no orders
                $product->delete();
                
                return response()->json([
                    'message' => 'Product deleted successfully',
                    'action' => 'deleted'
                ], Response::HTTP_OK);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => 'Something went wrong while deleting the product'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/products/bulk-update",
     *     summary="Bulk update products",
     *     description="Perform bulk operations on multiple products (activate, deactivate, feature, unfeature)",
     *     operationId="bulkUpdateProducts",
     *     tags={"Admin - Products"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_ids", "action"},
     *             @OA\Property(property="product_ids", type="array", minItems=1, @OA\Items(type="integer", example=1), description="Array of product IDs to update"),
     *             @OA\Property(property="action", type="string", enum={"activate", "deactivate", "feature", "unfeature"}, example="activate", description="Action to perform on selected products")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk update completed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Bulk activate completed successfully"),
     *             @OA\Property(property="updated_count", type="integer", example=5, description="Number of products updated")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'action' => ['required', 'in:activate,deactivate,feature,unfeature'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $productIds = $request->product_ids;
            $action = $request->action;

            $updateData = [];
            switch ($action) {
                case 'activate':
                    $updateData['is_active'] = true;
                    break;
                case 'deactivate':
                    $updateData['is_active'] = false;
                    break;
                case 'feature':
                    $updateData['featured'] = true;
                    break;
                case 'unfeature':
                    $updateData['featured'] = false;
                    break;
            }

            $updatedCount = Product::whereIn('id', $productIds)->update($updateData);

            return response()->json([
                'message' => "Bulk {$action} completed successfully",
                'updated_count' => $updatedCount
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to perform bulk update',
                'error' => 'Something went wrong during bulk update'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/products/stats",
     *     summary="Get product statistics",
     *     description="Retrieve comprehensive product statistics for admin dashboard",
     *     operationId="getProductStats",
     *     tags={"Admin - Products"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Product statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_products", type="integer", example=150, description="Total number of products"),
     *                 @OA\Property(property="active_products", type="integer", example=120, description="Number of active products"),
     *                 @OA\Property(property="inactive_products", type="integer", example=30, description="Number of inactive products"),
     *                 @OA\Property(property="featured_products", type="integer", example=25, description="Number of featured products"),
     *                 @OA\Property(property="out_of_stock", type="integer", example=15, description="Number of out-of-stock products"),
     *                 @OA\Property(property="low_stock", type="integer", example=8, description="Number of low stock products (1-10 items)"),
     *                 @OA\Property(property="total_stock_value", type="number", format="float", example=125000.50, description="Total value of all active product stock"),
     *                 @OA\Property(property="categories_count", type="integer", example=12, description="Number of active categories")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required")
     * )
     */
    public function stats()
    {
        try {
            $stats = [
                'total_products' => Product::count(),
                'active_products' => Product::where('is_active', true)->count(),
                'inactive_products' => Product::where('is_active', false)->count(),
                'featured_products' => Product::where('featured', true)->count(),
                'out_of_stock' => Product::where('stock_quantity', 0)->count(),
                'low_stock' => Product::where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 10)->count(),
                'total_stock_value' => Product::where('is_active', true)->sum(\DB::raw('price * stock_quantity')),
                'categories_count' => Category::where('is_active', true)->count(),
            ];

            return response()->json([
                'data' => $stats
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch product statistics',
                'error' => 'Something went wrong while fetching statistics'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}