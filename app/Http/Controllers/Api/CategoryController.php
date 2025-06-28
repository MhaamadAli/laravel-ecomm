<?php

/**
 * Category Controller - Handles product categories
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Api/CategoryController.php
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/categories",
     *     summary="Get all categories",
     *     description="Retrieve all active categories with optional hierarchy",
     *     operationId="getCategories",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="hierarchy",
     *         in="query",
     *         description="Include category hierarchy",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Parameter(
     *         name="include_empty",
     *         in="query", 
     *         description="Include categories without products",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices and gadgets"),
     *                     @OA\Property(property="image_url", type="string", example="https://example.com/category.jpg"),
     *                     @OA\Property(property="products_count", type="integer", example=25),
     *                     @OA\Property(property="children", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Smartphones"),
     *                             @OA\Property(property="slug", type="string", example="smartphones")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer", example=10),
     *                 @OA\Property(property="hierarchy", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $includeHierarchy = $request->boolean('hierarchy', true);
            $includeEmpty = $request->boolean('include_empty', false);

            if ($includeHierarchy) {
                // Get root categories with their direct children only
                $categories = Category::active()
                                    ->root()
                                    ->with(['children' => function($query) use ($includeEmpty) {
                                        $query->active()->limit(50); // Limit children to prevent memory issues
                                        if (!$includeEmpty) {
                                            $query->has('products');
                                        }
                                    }])
                                    ->when(!$includeEmpty, function($query) {
                                        $query->has('products');
                                    })
                                    ->orderBy('name')
                                    ->limit(100) // Limit root categories
                                    ->get();
            } else {
                // Get flat list of all categories
                $categories = Category::active()
                                    ->when(!$includeEmpty, function($query) {
                                        $query->has('products');
                                    })
                                    ->orderBy('name')
                                    ->get();
            }

            return response()->json([
                'data' => $categories,
                'meta' => [
                    'total' => $categories->count(),
                    'hierarchy' => $includeHierarchy,
                    'include_empty' => $includeEmpty
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch categories',
                'error' => 'Something went wrong while fetching categories'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/categories/{slug}",
     *     summary="Get single category details",
     *     description="Retrieve category details with hierarchy and subcategories",
     *     operationId="getCategory",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Category slug",
     *         required=true,
     *         @OA\Schema(type="string", example="electronics")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Electronics"),
     *                 @OA\Property(property="slug", type="string", example="electronics"),
     *                 @OA\Property(property="description", type="string", example="Electronic devices and gadgets"),
     *                 @OA\Property(property="products_count", type="integer", example=25)
     *             ),
     *             @OA\Property(property="hierarchy", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="subcategories", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="has_subcategories", type="boolean", example=true),
     *                 @OA\Property(property="is_root", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function show($slug)
    {
        try {
            $category = Category::active()
                              ->where('slug', $slug)
                              ->with(['parent', 'children' => function($query) {
                                  $query->active()->has('products');
                              }])
                              ->first();

            if (!$category) {
                return response()->json([
                    'message' => 'Category not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Get category hierarchy path
            $hierarchy = $category->getHierarchy();
            
            // Get subcategories if any
            $subcategories = $category->children()
                                    ->active()
                                    ->has('products')
                                    ->orderBy('name')
                                    ->get();

            return response()->json([
                'data' => $category,
                'hierarchy' => $hierarchy,
                'subcategories' => $subcategories,
                'meta' => [
                    'has_subcategories' => $subcategories->count() > 0,
                    'is_root' => $category->isRoot(),
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch category',
                'error' => 'Something went wrong while fetching category details'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/categories/{slug}/products",
     *     summary="Get category products",
     *     description="Retrieve products belonging to a specific category with filtering",
     *     operationId="getCategoryProducts",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Category slug",
     *         required=true,
     *         @OA\Schema(type="string", example="electronics")
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
     *     @OA\Parameter(
     *         name="in_stock",
     *         in="query",
     *         description="Filter in-stock products only",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="include_subcategories",
     *         in="query",
     *         description="Include products from subcategories",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="category", type="object",
     *                 @OA\Property(property="name", type="string", example="Electronics"),
     *                 @OA\Property(property="slug", type="string", example="electronics"),
     *                 @OA\Property(property="products_count", type="integer", example=25)
     *             ),
     *             @OA\Property(property="products", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="iPhone 15"),
     *                         @OA\Property(property="price", type="number", format="float", example=999.99)
     *                     )
     *                 ),
     *                 @OA\Property(property="meta", type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="total", type="integer", example=25)
     *                 )
     *             ),
     *             @OA\Property(property="subcategories", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function products($slug, Request $request)
    {
        // Validate query parameters
        $validator = Validator::make($request->all(), [
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'in:name,price,created_at,average_rating'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'in_stock' => ['sometimes', 'boolean'],
            'include_subcategories' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Find category
            $category = Category::active()
                              ->where('slug', $slug)
                              ->first();

            if (!$category) {
                return response()->json([
                    'message' => 'Category not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Build products query
            $query = Product::with(['category'])
                           ->active();

            // Include products from subcategories if requested
            if ($request->boolean('include_subcategories', true)) {
                $categoryIds = [$category->id];
                $categoryIds = array_merge($categoryIds, $category->getDescendantIds());
                $query->whereIn('category_id', $categoryIds);
            } else {
                $query->where('category_id', $category->id);
            }

            // Apply filters
            if ($request->has('min_price') || $request->has('max_price')) {
                $query->priceRange($request->min_price, $request->max_price);
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            if ($request->boolean('in_stock')) {
                $query->inStock();
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            if ($sortBy === 'average_rating') {
                $query->withAvg('approvedReviews', 'rating')
                      ->orderBy('approved_reviews_avg_rating', $sortDirection);
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            // Get subcategories for navigation
            $subcategories = $category->children()
                                    ->active()
                                    ->has('products')
                                    ->orderBy('name')
                                    ->get();

            return response()->json([
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'image_url' => $category->image_url,
                    'hierarchy_path' => $category->hierarchy_path,
                    'products_count' => $category->products_count,
                ],
                'products' => [
                    'data' => $products->items(),
                    'meta' => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                        'from' => $products->firstItem(),
                        'to' => $products->lastItem(),
                    ]
                ],
                'subcategories' => $subcategories,
                'filters_applied' => [
                    'min_price' => $request->min_price,
                    'max_price' => $request->max_price,
                    'search' => $request->search,
                    'in_stock' => $request->boolean('in_stock'),
                    'include_subcategories' => $request->boolean('include_subcategories', true),
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch category products',
                'error' => 'Something went wrong while fetching category products'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/categories/navigation",
     *     summary="Get category navigation tree",
     *     description="Retrieve simplified category tree for navigation menus",
     *     operationId="getCategoryNavigation",
     *     tags={"Categories"},
     *     @OA\Response(
     *         response=200,
     *         description="Category navigation retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="products_count", type="integer", example=25),
     *                     @OA\Property(property="children", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Smartphones"),
     *                             @OA\Property(property="slug", type="string", example="smartphones"),
     *                             @OA\Property(property="products_count", type="integer", example=15)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function navigation()
    {
        try {
            $categories = Category::active()
                                ->root()
                                ->has('products')
                                ->with(['children' => function($query) {
                                    $query->active()->has('products')->limit(10);
                                }])
                                ->orderBy('name')
                                ->get()
                                ->map(function ($category) {
                                    return [
                                        'id' => $category->id,
                                        'name' => $category->name,
                                        'slug' => $category->slug,
                                        'products_count' => $category->products_count,
                                        'children' => $category->children->map(function ($child) {
                                            return [
                                                'id' => $child->id,
                                                'name' => $child->name,
                                                'slug' => $child->slug,
                                                'products_count' => $child->products_count,
                                            ];
                                        })
                                    ];
                                });

            return response()->json([
                'data' => $categories
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch navigation categories',
                'error' => 'Something went wrong while fetching navigation'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/categories/search",
     *     summary="Search categories",
     *     description="Search for categories by name or description",
     *     operationId="searchCategories",
     *     tags={"Categories"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query",
     *         required=true,
     *         @OA\Schema(type="string", minLength=2, example="electronics")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices"),
     *                     @OA\Property(property="products_count", type="integer", example=25)
     *                 )
     *             ),
     *             @OA\Property(property="search", type="object",
     *                 @OA\Property(property="query", type="string", example="electronics"),
     *                 @OA\Property(property="total_results", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $searchTerm = $request->get('q');
            
            $categories = Category::active()
                                ->where(function($query) use ($searchTerm) {
                                    $query->where('name', 'ILIKE', "%{$searchTerm}%")
                                          ->orWhere('description', 'ILIKE', "%{$searchTerm}%");
                                })
                                ->has('products')
                                ->orderBy('name')
                                ->limit(20)
                                ->get();

            return response()->json([
                'data' => $categories,
                'search' => [
                    'query' => $searchTerm,
                    'total_results' => $categories->count(),
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Category search failed',
                'error' => 'Something went wrong during category search'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}