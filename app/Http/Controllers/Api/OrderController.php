<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display all orders milik user yang sedang login
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        $query = Order::with(['orderItems.product:id,name,price,image'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by status jika ada
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->get();

        // Transform data untuk response
        $ordersData = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'status' => $order->status,
                'total_price' => $order->total_price,
                'total_quantity' => $order->total_quantity,
                'total_items' => $order->total_items,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $ordersData,
            'message' => 'Orders retrieved successfully'
        ]);
    }

    /**
     * Create order baru (basic - biasanya pakai checkout)
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'status' => $request->status ?? 'pending',
        ]);

        return response()->json([
            'success' => true,
            'data' => $order,
            'message' => 'Order created successfully'
        ], 201);
    }

    /**
     * Display detail order spesifik
     */
    public function show(string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        $order = Order::with([
                'orderItems.product:id,name,price,image',
                'user:id,name,email'
            ])
            ->where('user_id', $user->id)
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $orderData = [
            'id' => $order->id,
            'status' => $order->status,
            'user' => $order->user,
            'total_price' => $order->total_price,
            'total_quantity' => $order->total_quantity,
            'total_items' => $order->total_items,
            'items' => $order->orderItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product' => $item->product,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ];
            }),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $orderData,
            'message' => 'Order detail retrieved successfully'
        ]);
    }

    /**
     * Update order (hanya status)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        $order = Order::where('user_id', $user->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'data' => $order,
            'message' => 'Order status updated successfully'
        ]);
    }

    /**
     * Delete order (hanya jika status pending)
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        $order = Order::where('user_id', $user->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Hanya bisa cancel jika status pending
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel order. Only pending orders can be cancelled.'
            ], 400);
        }

        $order->delete(); // Akan otomatis hapus order_items karena cascade

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    }
}
