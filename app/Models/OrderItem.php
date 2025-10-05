<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Auto-calculate subtotal saat create/update
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($orderItem) {
            $orderItem->subtotal = $orderItem->quantity * $orderItem->unit_price;
        });
    }

    /**
     * Relasi: OrderItem belongs to Order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relasi: OrderItem belongs to Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
