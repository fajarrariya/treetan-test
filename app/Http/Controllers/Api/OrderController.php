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
     * Display a listing of orders for authenticated user
     */
    public function index(): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        // Ambil orders milik user yang sedang login dengan data user
        $orders = Order::with('user:id,name,email')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
            'message' => 'Orders retrieved successfully'
        ]);
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();

        $validator = Validator::make($request->all(), [
            'total_price' => 'required|numeric|min:0',
            'status' => 'sometimes|in:pending,completed', // optional, default pending
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::create([
            'user_id' => $user->id, // Otomatis ambil dari user yang login
            'total_price' => $request->total_price,
            'status' => $request->status ?? 'pending', // Default pending jika tidak diisi
        ]);

        // Load relasi user untuk response
        $order->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'data' => $order,
            'message' => 'Order created successfully'
        ], 201);
    }

    /**
     * Display the specified order
     */
    public function show(string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        // Cari order milik user yang sedang login
        $order = Order::with('user:id,name,email')
            ->where('user_id', $user->id)
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or not authorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
            'message' => 'Order retrieved successfully'
        ]);
    }

    /**
     * Update the specified order
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        $order = Order::where('user_id', $user->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or not authorized'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,completed',
            'total_price' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order->update($request->only(['status', 'total_price']));
        $order->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'data' => $order,
            'message' => 'Order updated successfully'
        ]);
    }

    /**
     * Remove the specified order
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        $order = Order::where('user_id', $user->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or not authorized'
            ], 404);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);
    }

    /**
     * Get all orders (admin only - optional)
     */
    public function getAllOrders(): JsonResponse
    {
        $orders = Order::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
            'message' => 'All orders retrieved successfully'
        ]);
    }
}
