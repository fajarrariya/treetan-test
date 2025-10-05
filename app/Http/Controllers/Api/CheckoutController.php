<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Process checkout - Create Order + Order Items + Midtrans Payment
     */
    public function checkout(Request $request)
    {
        $user = auth('sanctum')->user();

        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            // 1. BUAT ORDER dulu (header/amplop)
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'payment_status' => 'unpaid', // ✅ Set payment status
            ]);

            $totalPrice = 0;

            // 2. BUAT ORDER_ITEMS (isi/detail) untuk setiap product
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                
                // Cek stock
                if (!$product) {
                    throw new \Exception("Product not found");
                }
                
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock}");
                }

                $unitPrice = $product->price;
                $subtotal = $item['quantity'] * $unitPrice;
                $totalPrice += $subtotal;

                // Create order item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                // Update stock product
                $product->decrement('stock', $item['quantity']);
            }

            // 3. ✅ CREATE MIDTRANS PAYMENT
            $snapToken = $this->paymentService->createTransaction($order);

            // ✅ Semua berhasil, commit transaction
            DB::commit();

            // Load relations untuk response
            $order->load(['orderItems.product:id,name,price,image', 'user:id,name,email']);

            return response()->json([
                'success' => true,
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'user' => $order->user,
                        'status' => $order->status,
                        'payment_status' => $order->payment_status, // ✅ Tambah payment status
                        'snap_token' => $order->snap_token,         // ✅ Tambah snap token
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
                    ],
                    'payment' => [ // ✅ Tambah payment info
                        'snap_token' => $snapToken,
                        'payment_url' => config('midtrans.is_production') 
                            ? "https://app.midtrans.com/snap/v2/vtweb/{$snapToken}"
                            : "https://app.sandbox.midtrans.com/snap/v2/vtweb/{$snapToken}",
                    ],
                    'summary' => [
                        'total_price' => $order->total_price,
                        'total_items' => $order->orderItems->count(),
                        'total_quantity' => $order->total_quantity,
                    ]
                ],
                'message' => 'Checkout successful! Please proceed to payment.'
            ], 201);

        } catch (\Exception $e) {
            // ❌ Ada error, rollback semua
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get checkout summary (untuk preview sebelum checkout)
     */
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $summary = [];
        $totalPrice = 0;
        $totalQuantity = 0;
        $errors = [];

        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            
            if (!$product) {
                $errors[] = "Product ID {$item['product_id']} not found";
                continue;
            }

            $subtotal = $item['quantity'] * $product->price;
            $totalPrice += $subtotal;
            $totalQuantity += $item['quantity'];

            $summary[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => $product->price,
                'quantity' => $item['quantity'],
                'subtotal' => $subtotal,
                'stock_available' => $product->stock,
                'stock_sufficient' => $product->stock >= $item['quantity'],
            ];
        }

        return response()->json([
            'success' => count($errors) === 0,
            'data' => [
                'items' => $summary,
                'summary' => [
                    'total_price' => $totalPrice,
                    'total_quantity' => $totalQuantity,
                    'total_items' => count($summary),
                ]
            ],
            'errors' => $errors,
            'message' => count($errors) === 0 ? 'Checkout summary generated' : 'Some items have issues'
        ]);
    }

    /**
     * ✅ UPDATED: Get payment status
     */
    public function getPaymentStatus($orderId)
    {
        $user = auth('sanctum')->user();
        
        $order = Order::where('user_id', $user->id)->find($orderId);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_type' => $order->payment_type,
                'transaction_id' => $order->transaction_id,
                'total_amount' => $order->total_amount,
                'snap_token' => $order->snap_token,
                'is_paid' => $order->isPaid(),
                'is_payment_pending' => $order->isPaymentPending(),
                'is_payment_failed' => $order->isPaymentFailed(),
            ],
            'message' => 'Payment status retrieved successfully'
        ]);
    }

    /**
     * ✅ UPDATED: Complete payment (simulasi atau manual)
     */
    public function completePayment(Request $request, $orderId)
    {
        $user = auth('sanctum')->user();
        
        $order = Order::where('user_id', $user->id)->find($orderId);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->payment_status !== 'unpaid') {
            return response()->json([
                'success' => false,
                'message' => 'Order payment is not pending'
            ], 400);
        }

        // ✅ Update payment status (simulasi manual)
        $order->update([
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_type' => $request->payment_type ?? 'manual', // Default manual
            'transaction_id' => $request->transaction_id ?? 'manual-' . time(),
        ]);

        $order->load(['orderItems.product', 'user']);

        return response()->json([
            'success' => true,
            'data' => $order,
            'message' => 'Payment completed successfully'
        ]);
    }

    /**
     * ✅ NEW: Cancel unpaid order
     */
    public function cancelOrder($orderId)
    {
        $user = auth('sanctum')->user();
        
        $order = Order::where('user_id', $user->id)->find($orderId);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel paid order'
            ], 400);
        }

        // Restore stock
        foreach ($order->orderItems as $item) {
            $item->product->increment('stock', $item->quantity);
        }

        // Update order status
        $order->update([
            'status' => 'cancelled',
            'payment_status' => 'failed'
        ]);

        return response()->json([
            'success' => true,
            'data' => $order,
            'message' => 'Order cancelled successfully'
        ]);
    }
}
