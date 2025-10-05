<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderItemController extends Controller
{
    /**
     * Display order items (bisa filter by order_id)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        $orderId = $request->query('order_id');

        if ($orderId) {
            // Cek apakah order milik user yang login
            $order = Order::where('user_id', $user->id)->find($orderId);
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or not authorized'
                ], 404);
            }

            $orderItems = OrderItem::with(['product:id,name,price,image'])
                ->where('order_id', $orderId)
                ->get();
        } else {
            // Ambil semua order items milik user
            $orderItems = OrderItem::with(['product:id,name,price,image', 'order:id,status'])
                ->whereHas('order', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $orderItems,
            'message' => 'Order items retrieved successfully'
        ]);
    }

    /**
     * Create order item baru
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah order milik user yang login
        $order = Order::where('user_id', $user->id)->find($request->order_id);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or not authorized'
            ], 404);
        }

        // Ambil product dan cek stock
        $product = Product::find($request->product_id);
        
        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock'
            ], 400);
        }

        $orderItem = OrderItem::create([
            'order_id' => $request->order_id,
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'unit_price' => $product->price, // Harga saat ini
            // subtotal auto-calculated di boot method
        ]);

        // Update stock product
        $product->decrement('stock', $request->quantity);

        $orderItem->load(['product:id,name,price', 'order:id,status']);

        return response()->json([
            'success' => true,
            'data' => $orderItem,
            'message' => 'Order item created successfully'
        ], 201);
    }

    /**
     * Show specific order item
     */
    public function show(string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        $orderItem = OrderItem::with(['product', 'order'])
            ->whereHas('order', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->find($id);

        if (!$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Order item not found or not authorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $orderItem,
            'message' => 'Order item retrieved successfully'
        ]);
    }

    /**
     * Update order item (hanya quantity)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        $orderItem = OrderItem::whereHas('order', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->find($id);

        if (!$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Order item not found or not authorized'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update stock product
        $product = $orderItem->product;
        $oldQuantity = $orderItem->quantity;
        $newQuantity = $request->quantity;
        $stockDiff = $newQuantity - $oldQuantity;

        if ($stockDiff > 0 && $product->stock < $stockDiff) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock'
            ], 400);
        }

        // Update order item
        $orderItem->update(['quantity' => $newQuantity]);
        
        // Update stock
        $product->decrement('stock', $stockDiff);

        $orderItem->load(['product:id,name,price', 'order:id,status']);

        return response()->json([
            'success' => true,
            'data' => $orderItem,
            'message' => 'Order item updated successfully'
        ]);
    }

    /**
     * Delete order item
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth('sanctum')->user();

        $orderItem = OrderItem::whereHas('order', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->find($id);

        if (!$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Order item not found or not authorized'
            ], 404);
        }

        // Restore stock
        $product = $orderItem->product;
        $product->increment('stock', $orderItem->quantity);

        $orderItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order item deleted successfully'
        ]);
    }
}
