<?php

/**
 * Admin Category Controller - Handles category management for administrators
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Admin/CategoryController.php
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/categories",
     *     summary="Get all categories for admin",
     *     description="Retrieve all categories with admin management options and filtering",
     *     operationId="getAdminCategories",
     *     tags={"Admin - Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by category status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "all"}, example="active")
     *     ),
     *     @OA\Parameter(
     *         name="parent_id",
     *         in="query",
     *         description="Filter by parent category ID (null for root categories)",
     *         required=false,
     *         @OA\Schema(type="integer", nullable=true, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         required=false,
     *         @OA\Schema(type="string", example="Electronics")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "created_at", "products_count"}, example="created_at")
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
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="description", type="string", example="Electronic devices"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="products_count", type="integer", example=25),
     *                     @OA\Property(property="active_products_count", type="integer", example=20),
     *                     @OA\Property(property="subcategories_count", type="integer", example=5),
     *                     @OA\Property(property="has_products", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="total", type="integer", example=50)
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
            'status' => ['sometimes', 'in:active,inactive,all'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'in:name,created_at,products_count'],
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
            // Build query with product counts
            $query = Category::withCount(['products', 'activeProducts']);

            // Apply status filter
            $status = $request->get('status', 'all');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }

            // Apply parent filter
            if ($request->has('parent_id')) {
                if ($request->parent_id === null || $request->parent_id === '') {
                    $query->whereNull('parent_id'); // Root categories
                } else {
                    $query->where('parent_id', $request->parent_id);
                }
            }

            // Apply search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate results
            $perPage = $request->get('per_page', 20);
            $categories = $query->paginate($perPage);

            // Add additional data for each category
            $categories->through(function ($category) {
                $category->makeVisible(['created_at', 'updated_at']);
                $category->setAttribute('subcategories_count', $category->children()->count());
                $category->setAttribute('has_products', $category->products_count > 0);
                return $category;
            });

            return response()->json([
                'data' => $categories->items(),
                'meta' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                ],
                'filters_applied' => [
                    'status' => $status,
                    'parent_id' => $request->parent_id,
                    'search' => $request->search,
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
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
     *     path="/api/admin/categories/{id}",
     *     summary="Get single category for admin",
     *     description="Get detailed category information for admin editing",
     *     operationId="getAdminCategory",
     *     tags={"Admin - Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Electronics"),
     *                 @OA\Property(property="slug", type="string", example="electronics"),
     *                 @OA\Property(property="description", type="string", example="Electronic devices"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="image", type="string", nullable=true, example="https://example.com/category.jpg"),
     *                 @OA\Property(property="products_count", type="integer", example=25),
     *                 @OA\Property(property="subcategories_count", type="integer", example=5),
     *                 @OA\Property(property="parent", type="object", nullable=true),
     *                 @OA\Property(property="children", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="products", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function show($id)
    {
        try {
            $category = Category::with(['parent', 'children', 'products'])
                              ->withCount(['products', 'activeProducts'])
                              ->findOrFail($id);

            $category->makeVisible(['created_at', 'updated_at']);
            $category->setAttribute('subcategories_count', $category->children()->count());

            return response()->json([
                'data' => $category
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Category not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch category',
                'error' => 'Something went wrong while fetching category details'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/categories",
     *     summary="Create new category",
     *     description="Create a new category with hierarchy support",
     *     operationId="createCategory",
     *     tags={"Admin - Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Smartphones"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Mobile phones and accessories"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, example=1, description="Parent category ID for hierarchy"),
     *             @OA\Property(property="image", type="string", nullable=true, maxLength=255, example="https://example.com/category.jpg"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category created successfully"),
     *             @OA\Property(property="category", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Smartphones"),
     *                 @OA\Property(property="slug", type="string", example="smartphones"),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
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
        // Validate category data
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'image' => ['nullable', 'string', 'max:255'], // URL for category image
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Validate parent category hierarchy (prevent circular references)
            if ($request->parent_id) {
                $parentCategory = Category::find($request->parent_id);
                if (!$parentCategory || !$parentCategory->is_active) {
                    return response()->json([
                        'message' => 'Invalid parent category'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            // Create category
            $category = Category::create([
                'name' => $request->name,
                'description' => $request->description,
                'parent_id' => $request->parent_id,
                'image' => $request->image,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $category->load(['parent']);

            return response()->json([
                'message' => 'Category created successfully',
                'category' => $category
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create category',
                'error' => 'Something went wrong while creating the category'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/admin/categories/{id}",
     *     summary="Update existing category",
     *     description="Update an existing category with validation for circular references",
     *     operationId="updateCategory",
     *     tags={"Admin - Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated Category Name"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Updated description"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true, example=2),
     *             @OA\Property(property="image", type="string", nullable=true, maxLength=255, example="https://example.com/new-image.jpg"),
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Category updated successfully"),
     *             @OA\Property(property="category", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Updated Category Name"),
     *                 @OA\Property(property="slug", type="string", example="updated-category-name")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Category not found"),
     *     @OA\Response(response=422, description="Validation error or circular reference")
     * )
     */
    public function update($id, Request $request)
    {
        try {
            $category = Category::findOrFail($id);

            // Validate update data
            $validator = Validator::make($request->all(), [
                'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:categories,name,' . $id],
                'description' => ['sometimes', 'nullable', 'string'],
                'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
                'image' => ['sometimes', 'nullable', 'string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Validate parent category (prevent self-reference and circular references)
            if ($request->has('parent_id') && $request->parent_id) {
                if ($request->parent_id == $id) {
                    return response()->json([
                        'message' => 'Category cannot be its own parent'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                // Check for circular reference
                $parentCategory = Category::find($request->parent_id);
                if ($parentCategory && $this->wouldCreateCircularReference($category, $parentCategory)) {
                    return response()->json([
                        'message' => 'This would create a circular reference in category hierarchy'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            // Update category
            $category->update($request->only([
                'name', 'description', 'parent_id', 'image', 'is_active'
            ]));

            $category->load(['parent']);

            return response()->json([
                'message' => 'Category updated successfully',
                'category' => $category
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Category not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update category',
                'error' => 'Something went wrong while updating the category'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/categories/{id}",
     *     summary="Delete or deactivate category",
     *     description="Delete category if no products/subcategories exist, otherwise deactivate it",
     *     operationId="deleteCategory",
     *     tags={"Admin - Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted or deactivated successfully",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Category deleted successfully"),
     *                     @OA\Property(property="action", type="string", example="deleted")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Category deactivated successfully (has existing products or subcategories)"),
     *                     @OA\Property(property="action", type="string", example="deactivated")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required"),
     *     @OA\Response(response=404, description="Category not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $category = Category::withCount(['products', 'children'])->findOrFail($id);

            // Check for existing products or subcategories
            if ($category->products_count > 0 || $category->children_count > 0) {
                // Deactivate instead of delete if category has products or subcategories
                $category->update(['is_active' => false]);
                
                return response()->json([
                    'message' => 'Category deactivated successfully (has existing products or subcategories)',
                    'action' => 'deactivated'
                ], Response::HTTP_OK);
            } else {
                // Safe to delete if no products or subcategories
                $category->delete();
                
                return response()->json([
                    'message' => 'Category deleted successfully',
                    'action' => 'deleted'
                ], Response::HTTP_OK);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Category not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete category',
                'error' => 'Something went wrong while deleting the category'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/categories/tree",
     *     summary="Get category tree structure",
     *     description="Retrieve hierarchical category tree for admin management",
     *     operationId="getAdminCategoryTree",
     *     tags={"Admin - Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Category tree retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="products_count", type="integer", example=25),
     *                     @OA\Property(property="active_products_count", type="integer", example=20),
     *                     @OA\Property(property="children", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Smartphones"),
     *                             @OA\Property(property="slug", type="string", example="smartphones"),
     *                             @OA\Property(property="children", type="array", @OA\Items(type="object"))
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized - Admin access required")
     * )
     */
    public function tree()
    {
        try {
            $categories = Category::with(['children.children'])
                                ->whereNull('parent_id')
                                ->withCount(['products', 'activeProducts'])
                                ->orderBy('name')
                                ->get();

            // Add counts for each category in the tree
            $this->addCountsToTree($categories);

            return response()->json([
                'data' => $categories
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch category tree',
                'error' => 'Something went wrong while fetching category tree'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/categories/stats",
     *     summary="Get category statistics",
     *     description="Retrieve comprehensive category statistics for admin dashboard",
     *     operationId="getCategoryStats",
     *     tags={"Admin - Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Category statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_categories", type="integer", example=25, description="Total number of categories"),
     *                 @OA\Property(property="active_categories", type="integer", example=20, description="Number of active categories"),
     *                 @OA\Property(property="inactive_categories", type="integer", example=5, description="Number of inactive categories"),
     *                 @OA\Property(property="root_categories", type="integer", example=8, description="Number of root (parent) categories"),
     *                 @OA\Property(property="categories_with_products", type="integer", example=15, description="Categories that have products"),
     *                 @OA\Property(property="empty_categories", type="integer", example=10, description="Categories without products")
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
                'total_categories' => Category::count(),
                'active_categories' => Category::where('is_active', true)->count(),
                'inactive_categories' => Category::where('is_active', false)->count(),
                'root_categories' => Category::whereNull('parent_id')->count(),
                'categories_with_products' => Category::has('products')->count(),
                'empty_categories' => Category::doesntHave('products')->count(),
            ];

            return response()->json([
                'data' => $stats
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch category statistics',
                'error' => 'Something went wrong while fetching statistics'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check if setting parent would create circular reference
     */
    private function wouldCreateCircularReference($category, $potentialParent)
    {
        $current = $potentialParent;
        while ($current && $current->parent_id) {
            if ($current->parent_id == $category->id) {
                return true;
            }
            $current = $current->parent;
        }
        return false;
    }

    /**
     * Add product counts to category tree
     */
    private function addCountsToTree($categories)
    {
        foreach ($categories as $category) {
            $category->makeVisible(['created_at', 'updated_at']);
            if ($category->children->isNotEmpty()) {
                $this->addCountsToTree($category->children);
            }
        }
    }
}