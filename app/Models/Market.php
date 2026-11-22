<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Market extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'location',
        'image_path',
    ];

    // The seller/owner of this market
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Alias for seller
    public function owner(): BelongsTo
    {
        return $this->seller();
    }

    // Direct order items for this market
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Get all orders that include products from this market
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, OrderItem::class, 'market_id', 'id', 'id', 'order_id')
            ->distinct();
    }

    // Direct products belonging to this market
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Worker users assigned to this market.
     */
    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'market_users')
            ->withTimestamps();
    }
}
