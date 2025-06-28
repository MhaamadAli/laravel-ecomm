<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class SanctumAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'Token not provided'
            ], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken) {
            return response()->json([
                'message' => 'Unauthenticated', 
                'error' => 'Invalid token'
            ], 401);
        }

        $user = $accessToken->tokenable;
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'error' => 'Token user not found'
            ], 401);
        }

        // Set the authenticated user
        auth()->setUser($user);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}