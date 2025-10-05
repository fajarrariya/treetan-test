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
}
