<?php

/**
 * Review Controller - Handles product reviews and ratings
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Api/ReviewController.php
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products/{productId}/reviews",
     *     summary="Get product reviews",
     *     description="Retrieve all reviews for a specific product",
     *     operationId="getProductReviews",
     *     tags={"Reviews"},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="rating",
     *         in="query",
     *         description="Filter by rating",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=5, example=5)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"rating", "created_at"}, example="created_at")
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
     *         @OA\Schema(type="integer", minimum=1, maximum=50, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="reviews", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="rating", type="integer", example=5),
     *                     @OA\Property(property="comment", type="string", example="Great product!"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="user", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=45)
     *             ),
     *             @OA\Property(property="statistics", type="object",
     *                 @OA\Property(property="average_rating", type="number", format="float", example=4.2),
     *                 @OA\Property(property="total_reviews", type="integer", example=45),
     *                 @OA\Property(property="rating_breakdown", type="object",
     *                     @OA\Property(property="5", type="integer", example=20),
     *                     @OA\Property(property="4", type="integer", example=15),
     *                     @OA\Property(property="3", type="integer", example=7),
     *                     @OA\Property(property="2", type="integer", example=2),
     *                     @OA\Property(property="1", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index($productId, Request $request)
    {
        // Validate query parameters
        $validator = Validator::make($request->all(), [
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'sort_by' => ['sometimes', 'in:rating,created_at'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Check if product exists
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Build query for approved reviews
            $query = Review::with(['user:id,name'])
                          ->where('product_id', $productId)
                          ->approved();

            // Apply rating filter
            if ($request->has('rating')) {
                $query->rating($request->rating);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate results
            $perPage = $request->get('per_page', 10);
            $reviews = $query->paginate($perPage);

            // Calculate statistics
            $statistics = [
                'average_rating' => $product->average_rating,
                'total_reviews' => $product->reviews_count,
                'rating_breakdown' => []
            ];

            // Get rating breakdown
            for ($i = 1; $i <= 5; $i++) {
                $count = Review::where('product_id', $productId)
                             ->approved()
                             ->where('rating', $i)
                             ->count();
                             
                $statistics['rating_breakdown'][$i] = [
                    'rating' => $i,
                    'count' => $count,
                    'percentage' => $statistics['total_reviews'] > 0 
                        ? round(($count / $statistics['total_reviews']) * 100) 
                        : 0
                ];
            }

            return response()->json([
                'data' => $reviews->items(),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
                'statistics' => $statistics,
                'filters_applied' => [
                    'rating' => $request->rating,
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch reviews',
                'error' => 'Something went wrong while fetching reviews'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/products/{productId}/reviews",
     *     summary="Create product review",
     *     description="Submit a review for a product with rating and comment",
     *     operationId="createProductReview",
     *     tags={"Reviews"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Product ID to review",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rating"},
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5, description="Rating from 1 to 5 stars"),
     *             @OA\Property(property="title", type="string", maxLength=255, example="Great product!", description="Review title (optional)"),
     *             @OA\Property(property="comment", type="string", maxLength=1000, example="This product exceeded my expectations. Highly recommended!", description="Review comment (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Review created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Review submitted successfully"),
     *             @OA\Property(property="review", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="rating", type="integer", example=5),
     *                 @OA\Property(property="title", type="string", example="Great product!"),
     *                 @OA\Property(property="comment", type="string", example="This product exceeded my expectations"),
     *                 @OA\Property(property="is_approved", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error or user already reviewed")
     * )
     */
    public function store($productId, Request $request)
    {
        // Validate review data
        $validator = Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();

            // Check if product exists
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if user already reviewed this product
            $existingReview = Review::where('user_id', $user->id)
                                  ->where('product_id', $productId)
                                  ->first();

            if ($existingReview) {
                return response()->json([
                    'message' => 'You have already reviewed this product'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Check if user has purchased the product (optional - can be disabled for demo)
            $hasPurchased = Order::where('user_id', $user->id)
                               ->whereHas('orderItems', function ($query) use ($productId) {
                                   $query->where('product_id', $productId);
                               })
                               ->whereIn('status', [Order::STATUS_DELIVERED])
                               ->exists();

            // For demo purposes, we'll allow reviews without purchase
            // In production, you might want to enforce this
            // if (!$hasPurchased) {
            //     return response()->json([
            //         'message' => 'You can only review products you have purchased'
            //     ], Response::HTTP_UNPROCESSABLE_ENTITY);
            // }

            // Create review
            $review = Review::create([
                'user_id' => $user->id,
                'product_id' => $productId,
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
                'is_approved' => true, // Auto-approve for demo (set to false for moderation)
            ]);

            $review->load(['user:id,name']);

            return response()->json([
                'message' => 'Review submitted successfully',
                'review' => $review
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create review',
                'error' => 'Something went wrong while creating the review'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/reviews/{id}",
     *     summary="Update product review",
     *     description="Update an existing review that belongs to the authenticated user",
     *     operationId="updateProductReview",
     *     tags={"Reviews"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review ID to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=4, description="Updated rating from 1 to 5 stars"),
     *             @OA\Property(property="title", type="string", maxLength=255, example="Updated title", description="Updated review title"),
     *             @OA\Property(property="comment", type="string", maxLength=1000, example="Updated review comment", description="Updated review comment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Review updated successfully"),
     *             @OA\Property(property="review", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="rating", type="integer", example=4),
     *                 @OA\Property(property="title", type="string", example="Updated title"),
     *                 @OA\Property(property="comment", type="string", example="Updated review comment"),
     *                 @OA\Property(property="is_approved", type="boolean", example=true),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Review not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update($id, Request $request)
    {
        // Validate review data
        $validator = Validator::make($request->all(), [
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $user = $request->user();

            // Find review
            $review = Review::where('id', $id)
                          ->where('user_id', $user->id)
                          ->first();

            if (!$review) {
                return response()->json([
                    'message' => 'Review not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Update review fields
            if ($request->has('rating')) {
                $review->rating = $request->rating;
            }
            if ($request->has('title')) {
                $review->title = $request->title;
            }
            if ($request->has('comment')) {
                $review->comment = $request->comment;
            }

            // Reset approval status when updated (for moderation)
            $review->is_approved = true; // Auto-approve for demo

            $review->save();
            $review->load(['user:id,name']);

            return response()->json([
                'message' => 'Review updated successfully',
                'review' => $review
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update review',
                'error' => 'Something went wrong while updating the review'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/reviews/{id}",
     *     summary="Delete product review",
     *     description="Delete a review that belongs to the authenticated user",
     *     operationId="deleteProductReview",
     *     tags={"Reviews"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Review ID to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Review deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Review not found")
     * )
     */
    public function destroy($id, Request $request)
    {
        try {
            $user = $request->user();

            // Find review
            $review = Review::where('id', $id)
                          ->where('user_id', $user->id)
                          ->first();

            if (!$review) {
                return response()->json([
                    'message' => 'Review not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $review->delete();

            return response()->json([
                'message' => 'Review deleted successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete review',
                'error' => 'Something went wrong while deleting the review'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/reviews/my-reviews",
     *     summary="Get user's reviews",
     *     description="Retrieve all reviews submitted by the authenticated user",
     *     operationId="getUserReviews",
     *     tags={"Reviews"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User reviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="rating", type="integer", example=5),
     *                     @OA\Property(property="title", type="string", example="Great product!"),
     *                     @OA\Property(property="comment", type="string", example="This product exceeded my expectations"),
     *                     @OA\Property(property="is_approved", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="product", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="iPhone 15"),
     *                         @OA\Property(property="slug", type="string", example="iphone-15"),
     *                         @OA\Property(property="images", type="array", @OA\Items(type="string"))
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myReviews(Request $request)
    {
        try {
            $user = $request->user();

            $reviews = Review::with(['product:id,name,slug,images'])
                           ->where('user_id', $user->id)
                           ->orderBy('created_at', 'desc')
                           ->paginate(10);

            return response()->json([
                'data' => $reviews->items(),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch your reviews',
                'error' => 'Something went wrong while fetching your reviews'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products/{productId}/can-review",
     *     summary="Check review eligibility",
     *     description="Check if the authenticated user can review a specific product",
     *     operationId="checkReviewEligibility",
     *     tags={"Reviews"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Product ID to check review eligibility",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review eligibility checked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="can_review", type="boolean", example=true, description="Whether user can review this product"),
     *             @OA\Property(property="has_reviewed", type="boolean", example=false, description="Whether user already reviewed this product"),
     *             @OA\Property(property="has_purchased", type="boolean", example=true, description="Whether user has purchased this product"),
     *             @OA\Property(property="reason", type="string", example="You can review this product", description="Explanation of eligibility status")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function canReview($productId, Request $request)
    {
        try {
            $user = $request->user();

            // Check if product exists
            $product = Product::find($productId);
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if user already reviewed this product
            $hasReviewed = Review::where('user_id', $user->id)
                               ->where('product_id', $productId)
                               ->exists();

            // Check if user has purchased the product
            $hasPurchased = Order::where('user_id', $user->id)
                               ->whereHas('orderItems', function ($query) use ($productId) {
                                   $query->where('product_id', $productId);
                               })
                               ->whereIn('status', [Order::STATUS_DELIVERED])
                               ->exists();

            $canReview = !$hasReviewed; // For demo, only check if not already reviewed
            // In production: $canReview = !$hasReviewed && $hasPurchased;

            return response()->json([
                'can_review' => $canReview,
                'has_reviewed' => $hasReviewed,
                'has_purchased' => $hasPurchased,
                'reason' => $hasReviewed 
                    ? 'You have already reviewed this product'
                    : ($canReview ? 'You can review this product' : 'You need to purchase this product first')
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check review eligibility',
                'error' => 'Something went wrong while checking review eligibility'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}