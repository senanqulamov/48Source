<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketOrder extends Model
{
    public const STATUSES = ['placed' => 'Placed', 'confirmed' => 'Confirmed', 'ready' => 'Ready', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

    public const FLOW = ['placed', 'confirmed', 'ready', 'delivered'];

    protected $fillable = ['number', 'buyer_id', 'supplier_id', 'store_id', 'rfq_id', 'status', 'currency', 'total_cents', 'deliver_to', 'note', 'cancel_reason', 'confirmed_at', 'ready_at', 'delivered_at', 'cancelled_at'];
    protected $attributes = ['status' => 'placed', 'currency' => 'USD', 'total_cents' => 0, 'supplier_id' => null, 'rfq_id' => null, 'deliver_to' => null, 'note' => null, 'cancel_reason' => null, 'confirmed_at' => null, 'ready_at' => null, 'delivered_at' => null, 'cancelled_at' => null];

    protected function casts(): array
    {
        return ['total_cents' => 'integer', 'confirmed_at' => 'datetime', 'ready_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketOrderItem::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkerTask::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /** Buyers see what they or their company bought; a store sees what it sold. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return match (true) {
            $user->role === 'admin' => $q,
            in_array($user->role, ['seller', 'market_worker'], true) && $user->store_id !== null => $q->where('store_id', $user->store_id),
            $user->role === 'supplier' && $user->supplier_id !== null => $q->where('supplier_id', $user->supplier_id),
            $user->role === 'buyer' => $q->where('buyer_id', $user->id),
            default => $q->whereRaw('1 = 0'),
        };
    }
}
