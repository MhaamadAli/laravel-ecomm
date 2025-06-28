<?php

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+1234567890"),
 *     @OA\Property(property="role", type="string", enum={"user", "admin"}, example="user"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     title="Product",
 *     description="Product model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="iPhone 15"),
 *     @OA\Property(property="slug", type="string", example="iphone-15"),
 *     @OA\Property(property="description", type="string", example="Latest iPhone with advanced features"),
 *     @OA\Property(property="short_description", type="string", example="Latest iPhone"),
 *     @OA\Property(property="price", type="number", format="float", example=999.99),
 *     @OA\Property(property="sale_price", type="number", format="float", nullable=true, example=899.99),
 *     @OA\Property(property="sku", type="string", example="IP15-001"),
 *     @OA\Property(property="stock_quantity", type="integer", example=50),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="images", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="featured", type="boolean", example=false),
 *     @OA\Property(property="effective_price", type="number", format="float", example=899.99),
 *     @OA\Property(property="average_rating", type="number", format="float", example=4.5),
 *     @OA\Property(property="reviews_count", type="integer", example=25),
 *     @OA\Property(property="is_in_stock", type="boolean", example=true),
 *     @OA\Property(property="discount_percentage", type="integer", nullable=true, example=10),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     title="Category",
 *     description="Category model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Electronics"),
 *     @OA\Property(property="slug", type="string", example="electronics"),
 *     @OA\Property(property="description", type="string", example="Electronic devices and gadgets"),
 *     @OA\Property(property="image", type="string", example="https://example.com/image.jpg"),
 *     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="products_count", type="integer", example=25),
 *     @OA\Property(property="hierarchy_path", type="string", example="Electronics"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="CartItem",
 *     type="object",
 *     title="CartItem",
 *     description="Shopping cart item",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="subtotal", type="number", format="float", example=1799.98),
 *     @OA\Property(property="product", ref="#/components/schemas/Product"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     title="Order",
 *     description="Customer order",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="order_number", type="string", example="ORD-2025-000001"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "shipped", "delivered", "cancelled"}, example="pending"),
 *     @OA\Property(property="total_amount", type="number", format="float", example=1999.99),
 *     @OA\Property(property="shipping_address", type="object"),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="status_label", type="string", example="Pending"),
 *     @OA\Property(property="items_count", type="integer", example=3),
 *     @OA\Property(property="can_be_cancelled", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     title="Error",
 *     description="Error response",
 *     @OA\Property(property="message", type="string", example="An error occurred"),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 * 
 * @OA\Schema(
 *     schema="Success",
 *     type="object",
 *     title="Success",
 *     description="Success response",
 *     @OA\Property(property="message", type="string", example="Operation completed successfully"),
 *     @OA\Property(property="data", type="object", nullable=true)
 * )
 */