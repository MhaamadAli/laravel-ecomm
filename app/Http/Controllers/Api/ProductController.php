<?php

/**
 * Product Controller - Handles product browsing and search
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Api/ProductController.php
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     summary="Get products with filters",
     *     description="Retrieve paginated list of products with filtering and sorting options",
     *     operationId="getProducts",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=10.00)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=1000.00)
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
     *         description="Sort field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price", "created_at", "average_rating"}, example="price")
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="asc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         description="Filter featured products only",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="in_stock",
     *         in="query",
     *         description="Filter in-stock products only",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="iPhone 15"),
     *                         @OA\Property(property="price", type="number", format="float", example=999.99),
     *                         @OA\Property(property="sale_price", type="number", format="float", example=899.99),
     *                         @OA\Property(property="stock_quantity", type="integer", example=50),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="featured", type="boolean", example=false)
     *                     )
     *                 ),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=150)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Validate query parameters
        $validator = Validator::make($request->all(), [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'in:name,price,created_at,average_rating'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'featured' => ['sometimes', 'boolean'],
            'in_stock' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Start with active products
            $query = Product::with(['category', 'approvedReviews'])
                           ->active();

            // Apply filters
            if ($request->has('category_id')) {
                $query->byCategory($request->category_id);
            }

            if ($request->has('min_price') || $request->has('max_price')) {
                $query->priceRange($request->min_price, $request->max_price);
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->boolean('featured')) {
                $query->featured();
            }

            if ($request->boolean('in_stock')) {
                $query->inStock();
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            if ($sortBy === 'average_rating') {
                // Special handling for average rating sort
                $query->withAvg('approvedReviews', 'rating')
                      ->orderBy('approved_reviews_avg_rating', $sortDirection);
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
                'filters_applied' => [
                    'category_id' => $request->category_id,
                    'min_price' => $request->min_price,
                    'max_price' => $request->max_price,
                    'search' => $request->search,
                    'featured' => $request->boolean('featured'),
                    'in_stock' => $request->boolean('in_stock'),
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
     *     path="/api/products/{slug}",
     *     summary="Get single product details",
     *     description="Retrieve detailed information about a specific product by slug",
     *     operationId="getProduct",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Product slug",
     *         required=true,
     *         @OA\Schema(type="string", example="iphone-15")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="iPhone 15"),
     *                 @OA\Property(property="slug", type="string", example="iphone-15"),
     *                 @OA\Property(property="description", type="string", example="Latest iPhone with advanced features"),
     *                 @OA\Property(property="price", type="number", format="float", example=999.99),
     *                 @OA\Property(property="sale_price", type="number", format="float", example=899.99),
     *                 @OA\Property(property="stock_quantity", type="integer", example=50),
     *                 @OA\Property(property="average_rating", type="number", format="float", example=4.5),
     *                 @OA\Property(property="reviews_count", type="integer", example=128),
     *                 @OA\Property(property="category", type="object"),
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string"))
     *             ),
     *             @OA\Property(property="related_products", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="iPhone 14"),
     *                     @OA\Property(property="price", type="number", format="float", example=799.99)
     *                 )
     *             ),
     *             @OA\Property(property="review_statistics", type="object",
     *                 @OA\Property(property="average_rating", type="number", format="float", example=4.5),
     *                 @OA\Property(property="total_reviews", type="integer", example=128),
     *                 @OA\Property(property="rating_breakdown", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function show($slug)
    {
        try {
            $product = Product::with([
                'category',
                'approvedReviews' => function($query) {
                    $query->with('user:id,name')->latest()->limit(10);
                },
                'approvedReviews.user'
            ])->active()
              ->where('slug', $slug)
              ->first();

            if (!$product) {
                return response()->json([
                    'message' => 'Product not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Get related products
            $relatedProducts = $product->getRelatedProducts(4);

            // Calculate review statistics
            $reviewStats = [
                'average_rating' => $product->average_rating,
                'total_reviews' => $product->reviews_count,
                'rating_breakdown' => []
            ];

            // Get rating breakdown (1-5 stars)
            for ($i = 1; $i <= 5; $i++) {
                $count = $product->approvedReviews()->where('rating', $i)->count();
                $reviewStats['rating_breakdown'][$i] = [
                    'rating' => $i,
                    'count' => $count,
                    'percentage' => $product->reviews_count > 0 ? round(($count / $product->reviews_count) * 100) : 0
                ];
            }

            return response()->json([
                'data' => $product,
                'related_products' => $relatedProducts,
                'review_statistics' => $reviewStats
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch product',
                'error' => 'Something went wrong while fetching product details'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products/search",
     *     summary="Search products",
     *     description="Search for products with filters and sorting",
     *     operationId="searchProducts",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query",
     *         required=true,
     *         @OA\Schema(type="string", minLength=2, example="iPhone")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=100.00)
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float", example=2000.00)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price", "created_at", "average_rating"}, example="created_at")
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
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="iPhone 15"),
     *                     @OA\Property(property="price", type="number", format="float", example=999.99),
     *                     @OA\Property(property="category", type="object")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=25)
     *             ),
     *             @OA\Property(property="search", type="object",
     *                 @OA\Property(property="query", type="string", example="iPhone"),
     *                 @OA\Property(property="total_results", type="integer", example=25)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function search(Request $request)
    {
        // Validate search query
        $validator = Validator::make($request->all(), [
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'sort_by' => ['sometimes', 'in:name,price,created_at,average_rating'],
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
            $searchTerm = $request->get('q');
            
            // Build search query
            $query = Product::with(['category'])
                           ->active()
                           ->search($searchTerm);

            // Apply additional filters
            if ($request->has('category_id')) {
                $query->byCategory($request->category_id);
            }

            if ($request->has('min_price') || $request->has('max_price')) {
                $query->priceRange($request->min_price, $request->max_price);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
                'search' => [
                    'query' => $searchTerm,
                    'total_results' => $products->total(),
                    'filters_applied' => [
                        'category_id' => $request->category_id,
                        'min_price' => $request->min_price,
                        'max_price' => $request->max_price,
                    ]
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Search failed',
                'error' => 'Something went wrong during product search'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products/featured",
     *     summary="Get featured products",
     *     description="Retrieve featured products for homepage display",
     *     operationId="getFeaturedProducts",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of featured products to return",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Featured products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="featured_products", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="iPhone 15"),
     *                     @OA\Property(property="slug", type="string", example="iphone-15"),
     *                     @OA\Property(property="price", type="number", format="float", example=999.99),
     *                     @OA\Property(property="sale_price", type="number", format="float", example=899.99),
     *                     @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg"),
     *                     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
     *                     @OA\Property(property="reviews_count", type="integer", example=128),
     *                     @OA\Property(property="is_in_stock", type="boolean", example=true),
     *                     @OA\Property(property="category", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Electronics")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total_featured", type="integer", example=12),
     *                 @OA\Property(property="limit", type="integer", example=12)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function featured(Request $request)
    {
        try {
            $limit = $request->get('limit', 12);
            
            // Validate limit
            if ($limit > 50) {
                $limit = 50;
            }

            $featuredProducts = Product::with(['category'])
                                     ->active()
                                     ->featured()
                                     ->inStock()
                                     ->orderBy('created_at', 'desc')
                                     ->limit($limit)
                                     ->get();

            return response()->json([
                'data' => $featuredProducts,
                'meta' => [
                    'total' => $featuredProducts->count(),
                    'limit' => $limit
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch featured products',
                'error' => 'Something went wrong while fetching featured products'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products/filters",
     *     summary="Get product filter options",
     *     description="Retrieve available filter options for product browsing",
     *     operationId="getProductFilters",
     *     tags={"Products"},
     *     @OA\Response(
     *         response=200,
     *         description="Filter options retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="price_range", type="object",
     *                 @OA\Property(property="min", type="number", format="float", example=9.99),
     *                 @OA\Property(property="max", type="number", format="float", example=2999.99)
     *             ),
     *             @OA\Property(property="categories", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="products_count", type="integer", example=25)
     *                 )
     *             ),
     *             @OA\Property(property="sort_options", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="value", type="string", example="created_at"),
     *                     @OA\Property(property="label", type="string", example="Newest First")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function filters()
    {
        try {
            // Get price range
            $priceRange = Product::active()->selectRaw('MIN(price) as min_price, MAX(price) as max_price')->first();
            
            // Get categories with product counts
            $categories = Category::active()
                                ->with('products')
                                ->get()
                                ->map(function ($category) {
                                    return [
                                        'id' => $category->id,
                                        'name' => $category->name,
                                        'slug' => $category->slug,
                                        'products_count' => $category->products_count,
                                    ];
                                })
                                ->where('products_count', '>', 0)
                                ->values();

            return response()->json([
                'price_range' => [
                    'min' => $priceRange->min_price ?? 0,
                    'max' => $priceRange->max_price ?? 0,
                ],
                'categories' => $categories,
                'sort_options' => [
                    ['value' => 'created_at', 'label' => 'Newest First'],
                    ['value' => 'name', 'label' => 'Name A-Z'],
                    ['value' => 'price', 'label' => 'Price Low to High'],
                    ['value' => 'average_rating', 'label' => 'Highest Rated'],
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch filter data',
                'error' => 'Something went wrong while fetching filter options'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}