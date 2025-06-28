<?php

/**
 * Admin Middleware - Ensures only admin users can access admin routes
 * File: /mnt/c/Users/malia/OneDrive/Desktop/fyp/backend/app/Http/Middleware/AdminMiddleware.php
 */

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated. Please login to access this resource.',
                'error_code' => 'UNAUTHENTICATED'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user has admin role
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
                'error_code' => 'INSUFFICIENT_PERMISSIONS'
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}