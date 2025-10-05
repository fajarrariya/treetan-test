<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use App\Models\Order;

class PaymentService
{
    public function __construct()
    {
        // Set your Merchant Server Key
        Config::$serverKey = config('midtrans.server_key');
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        Config::$isProduction = config('midtrans.is_production');
        // Set sanitization on (default)
        Config::$isSanitized = config('midtrans.is_sanitized');
        // Set 3DS transaction for credit card to true
        Config::$is3ds = config('midtrans.is_3ds');
    }

    public function createTransaction(Order $order)
    {
        $params = [
            'transaction_details' => [
                'order_id' => $order->id,
                'gross_amount' => $order->total_price,
            ],
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
            ],
            'item_details' => $this->getItemDetails($order),
        ];

        try {
            // Get Snap Token
            $snapToken = Snap::getSnapToken($params);
            
            // Update order with snap token and total amount
            $order->update([
                'snap_token' => $snapToken,
                'total_amount' => $order->total_price,
                'payment_status' => 'unpaid'
            ]);

            return $snapToken;
        } catch (\Exception $e) {
            throw new \Exception('Failed to create payment: ' . $e->getMessage());
        }
    }

    private function getItemDetails(Order $order)
    {
        $items = [];
        
        foreach ($order->orderItems as $item) {
            $items[] = [
                'id' => $item->product->id,
                'price' => $item->unit_price,
                'quantity' => $item->quantity,
                'name' => $item->product->name,
            ];
        }

        return $items;
    }
}
