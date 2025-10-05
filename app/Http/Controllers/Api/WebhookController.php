<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Midtrans webhook notification
     */
    public function midtransNotification(Request $request)
    {
        try {
            $serverKey = config('midtrans.server_key');
            
            // Create signature key for validation
            $signatureKey = hash("sha512", 
                $request->order_id . 
                $request->status_code . 
                $request->gross_amount . 
                $serverKey
            );

            // Validate signature key
            if ($signatureKey !== $request->signature_key) {
                Log::error('Invalid Midtrans signature key');
                return response()->json(['message' => 'Invalid signature key'], 403);
            }

            // Find order
            $order = Order::find($request->order_id);
            
            if (!$order) {
                Log::error('Order not found: ' . $request->order_id);
                return response()->json(['message' => 'Order not found'], 404);
            }

            // Update order based on transaction status
            $this->updateOrderStatus($order, $request);

            Log::info('Midtrans webhook processed successfully for order: ' . $order->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Midtrans webhook error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed'
            ], 500);
        }
    }

    private function updateOrderStatus(Order $order, Request $request)
    {
        $transactionStatus = $request->transaction_status;
        $paymentType = $request->payment_type;
        $transactionId = $request->transaction_id;

        // Update payment details
        $order->update([
            'payment_type' => $paymentType,
            'transaction_id' => $transactionId,
        ]);

        switch ($transactionStatus) {
            case 'capture':
                if ($request->fraud_status == 'accept') {
                    $order->update([
                        'payment_status' => 'paid',
                        'status' => 'completed'
                    ]);
                }
                break;
                
            case 'settlement':
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'completed'
                ]);
                break;
                
            case 'pending':
                $order->update([
                    'payment_status' => 'unpaid'
                ]);
                break;
                
            case 'deny':
            case 'expire':
            case 'cancel':
                $order->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled'
                ]);
                
                // Restore stock
                foreach ($order->orderItems as $item) {
                    $item->product->increment('stock', $item->quantity);
                }
                break;
        }

        Log::info("Order {$order->id} status updated: payment_status={$order->payment_status}, status={$order->status}");
    }
}
