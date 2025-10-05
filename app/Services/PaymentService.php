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
    // ✅ BUAT ORDER ID UNIQUE
    $uniqueOrderId = $order->id . '-' . time(); // Contoh: "1-1728159600"
    
    $params = [
        'transaction_details' => [
            'order_id' => $uniqueOrderId,  // ✅ Pakai unique ID
            'gross_amount' => $order->total_price,
        ],
        'customer_details' => [
            'first_name' => $order->user->name,
            'email' => $order->user->email,
        ],
        'item_details' => $this->getItemDetails($order),
    ];

    try {
        $snapToken = Snap::getSnapToken($params);
        
        // Update order dengan snap token dan unique order id
        $order->update([
            'snap_token' => $snapToken,
            'total_amount' => $order->total_price,
            'payment_status' => 'unpaid',
            'transaction_id' => $uniqueOrderId // ✅ Simpan unique ID
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
