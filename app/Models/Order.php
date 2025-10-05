<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'snap_token',
        'payment_status',
        'payment_type',
        'transaction_id',
        'total_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relasi: Order belongs to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi: Order has many OrderItems
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Accessor: Calculate total price dari order items
     */
    public function getTotalPriceAttribute()
    {
        return $this->orderItems->sum('subtotal');
    }

    /**
     * Accessor: Calculate total quantity
     */
    public function getTotalQuantityAttribute()
    {
        return $this->orderItems->sum('quantity');
    }

    /**
     * Accessor: Count total items
     */
    public function getTotalItemsAttribute()
    {
        return $this->orderItems->count();
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by payment status
     */
    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    /**
     * Scope: Recent orders
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Check if order is paid
     */
    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Check if order payment is pending
     */
    public function isPaymentPending()
    {
        return $this->payment_status === 'unpaid';
    }

    /**
     * Check if order payment failed
     */
    public function isPaymentFailed()
    {
        return $this->payment_status === 'failed';
    }
}
