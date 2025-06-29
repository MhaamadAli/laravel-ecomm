<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - {{ $order->order_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #3B82F6;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .order-details {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .order-items {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .order-items th,
        .order-items td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .order-items th {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9fafb;
        }
        .btn {
            display: inline-block;
            background-color: #3B82F6;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .shipping-address {
            background-color: #f0f9ff;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #3B82F6;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Order Confirmation</h1>
        <p>Thank you for your order!</p>
    </div>

    <div class="content">
        <p>Hi {{ $user->name }},</p>
        
        <p>We're excited to confirm that we've received your order and it's being processed. Here are your order details:</p>

        <div class="order-details">
            <h2>Order Information</h2>
            <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
            <p><strong>Order Date:</strong> {{ $order->created_at->format('F j, Y \a\t g:i A') }}</p>
            <p><strong>Order Status:</strong> {{ ucfirst($order->status) }}</p>
            <p><strong>Total Amount:</strong> ${{ number_format($order->total_amount, 2) }}</p>
        </div>

        <h3>Order Items</h3>
        <table class="order-items">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>${{ number_format($item->price, 2) }}</td>
                    <td>${{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3"><strong>Total</strong></td>
                    <td><strong>${{ number_format($order->total_amount, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>

        <h3>Shipping Address</h3>
        <div class="shipping-address">
            @if($order->shipping_address)
                <p><strong>{{ $order->shipping_address['name'] ?? $user->name }}</strong></p>
                <p>{{ $order->shipping_address['address_line_1'] }}</p>
                @if(!empty($order->shipping_address['address_line_2']))
                    <p>{{ $order->shipping_address['address_line_2'] }}</p>
                @endif
                <p>{{ $order->shipping_address['city'] }}, {{ $order->shipping_address['state'] }} {{ $order->shipping_address['postal_code'] }}</p>
                <p>{{ $order->shipping_address['country'] }}</p>
                @if(!empty($order->shipping_address['phone']))
                    <p><strong>Phone:</strong> {{ $order->shipping_address['phone'] }}</p>
                @endif
            @endif
        </div>

        @if($order->notes)
        <h3>Order Notes</h3>
        <div class="shipping-address">
            <p>{{ $order->notes }}</p>
        </div>
        @endif

        <p>We'll send you another email with tracking information once your order ships. You can also track your order status anytime by visiting your account.</p>

        <center>
            <a href="{{ env('APP_URL') }}/orders/{{ $order->order_number }}" class="btn">View Order Details</a>
        </center>

        <p>Thank you for choosing our store!</p>

        <p>Best regards,<br>
        The E-Commerce Team</p>
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>If you have any questions, please contact our customer support.</p>
    </div>
</body>
</html>