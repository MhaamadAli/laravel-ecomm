<?php

/**
 * File Controller - Handles file uploads for products, categories, and user profiles
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Controllers/Api/FileController.php
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/upload/image",
     *     summary="Upload single image",
     *     description="Upload a single image file with optional resizing and optimization",
     *     operationId="uploadImage",
     *     tags={"File Upload"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image", "type"},
     *                 @OA\Property(property="image", type="string", format="binary", description="Image file to upload"),
     *                 @OA\Property(property="type", type="string", enum={"product", "category", "profile"}, example="product", description="Type of image being uploaded"),
     *                 @OA\Property(property="resize", type="boolean", example=true, description="Whether to resize the image"),
     *                 @OA\Property(property="width", type="integer", example=800, description="Target width for resizing"),
     *                 @OA\Property(property="height", type="integer", example=600, description="Target height for resizing")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Image uploaded successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="filename", type="string", example="products/1640123456_image.jpg"),
     *                 @OA\Property(property="original_name", type="string", example="image.jpg"),
     *                 @OA\Property(property="url", type="string", example="http://localhost/storage/products/1640123456_image.jpg"),
     *                 @OA\Property(property="size", type="integer", example=245760),
     *                 @OA\Property(property="type", type="string", example="image/jpeg"),
     *                 @OA\Property(property="dimensions", type="object",
     *                     @OA\Property(property="width", type="integer", example=800),
     *                     @OA\Property(property="height", type="integer", example=600)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=413, description="File too large")
     * )
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120' // 5MB max
            ],
            'type' => ['required', 'in:product,category,profile'],
            'resize' => ['sometimes', 'boolean'],
            'width' => ['sometimes', 'integer', 'min:50', 'max:2000'],
            'height' => ['sometimes', 'integer', 'min:50', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $file = $request->file('image');
            $type = $request->input('type');
            
            // Generate unique filename
            $timestamp = time();
            $extension = $file->getClientOriginalExtension();
            $filename = $timestamp . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;
            
            // Determine storage path based on type
            $storagePath = $this->getStoragePath($type);
            $fullPath = $storagePath . '/' . $filename;

            // Store the file (basic version without image processing library)
            $path = $file->storeAs($storagePath, $filename, 'public');

            // Get file info
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            // Generate public URL
            $url = Storage::disk('public')->url($path);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'data' => [
                    'filename' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'url' => $url,
                    'size' => $fileSize,
                    'type' => $mimeType,
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => 'Something went wrong during image upload'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/upload/images/multiple",
     *     summary="Upload multiple images",
     *     description="Upload multiple images at once (useful for product galleries)",
     *     operationId="uploadMultipleImages",
     *     tags={"File Upload"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"images", "type"},
     *                 @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="Array of image files to upload"
     *                 ),
     *                 @OA\Property(property="type", type="string", enum={"product", "category"}, example="product"),
     *                 @OA\Property(property="resize", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Images uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="5 images uploaded successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="uploaded", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="count", type="object",
     *                     @OA\Property(property="total", type="integer", example=5),
     *                     @OA\Property(property="successful", type="integer", example=5),
     *                     @OA\Property(property="failed", type="integer", example=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function uploadMultipleImages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120' // 5MB max per file
            ],
            'type' => ['required', 'in:product,category'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $files = $request->file('images');
            $type = $request->input('type');
            $uploaded = [];
            $failed = [];

            foreach ($files as $index => $file) {
                try {
                    // Generate unique filename
                    $timestamp = time() . '_' . $index;
                    $extension = $file->getClientOriginalExtension();
                    $filename = $timestamp . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension;
                    
                    // Determine storage path
                    $storagePath = $this->getStoragePath($type);
                    
                    // Store the file
                    $path = $file->storeAs($storagePath, $filename, 'public');
                    
                    // Generate public URL
                    $url = Storage::disk('public')->url($path);

                    $uploaded[] = [
                        'filename' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'url' => $url,
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                    ];

                } catch (\Exception $e) {
                    $failed[] = [
                        'index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'error' => 'Processing failed'
                    ];
                }
            }

            $successCount = count($uploaded);
            $failedCount = count($failed);
            $totalCount = $successCount + $failedCount;

            return response()->json([
                'message' => "{$successCount} of {$totalCount} images uploaded successfully",
                'data' => [
                    'uploaded' => $uploaded,
                    'failed' => $failed,
                    'count' => [
                        'total' => $totalCount,
                        'successful' => $successCount,
                        'failed' => $failedCount,
                    ]
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload images',
                'error' => 'Something went wrong during multiple image upload'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/upload/image",
     *     summary="Delete uploaded image",
     *     description="Delete an image from storage",
     *     operationId="deleteImage",
     *     tags={"File Upload"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"filename"},
     *             @OA\Property(property="filename", type="string", example="products/1640123456_image.jpg", description="Filename/path of the image to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Image deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Image not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function deleteImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filename' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $filename = $request->input('filename');

            // Security check: ensure filename is within allowed directories
            if (!$this->isValidImagePath($filename)) {
                return response()->json([
                    'message' => 'Invalid file path'
                ], Response::HTTP_FORBIDDEN);
            }

            if (!Storage::disk('public')->exists($filename)) {
                return response()->json([
                    'message' => 'Image not found'
                ], Response::HTTP_NOT_FOUND);
            }

            Storage::disk('public')->delete($filename);

            return response()->json([
                'message' => 'Image deleted successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete image',
                'error' => 'Something went wrong during image deletion'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get storage path based on upload type
     */
    private function getStoragePath($type)
    {
        return match($type) {
            'product' => 'products',
            'category' => 'categories',
            'profile' => 'profiles',
            default => 'misc'
        };
    }

    /**
     * Validate image path for security
     */
    private function isValidImagePath($path)
    {
        $allowedPaths = ['products/', 'categories/', 'profiles/', 'misc/'];
        
        foreach ($allowedPaths as $allowedPath) {
            if (str_starts_with($path, $allowedPath)) {
                return true;
            }
        }
        
        return false;
    }
}
