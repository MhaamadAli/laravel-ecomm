<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="E-Commerce API",
 *     version="1.0.0",
 *     description="E-Commerce Platform Backend API - University Final Year Project",
 *     @OA\Contact(
 *         email="admin@ecommerce.local"
 *     )
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter token in format (Bearer <token>)"
 * )
 */
abstract class Controller
{
    //
}
