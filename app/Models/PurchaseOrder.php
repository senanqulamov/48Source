<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const STATUSES = ['draft' => 'Draft', 'issued' => 'Issued', 'acknowledged' => 'Acknowledged', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'invoiced' => 'Invoiced', 'cancelled' => 'Cancelled'];

    public const FLOW = ['draft', 'issued', 'acknowledged', 'in_transit', 'delivered', 'invoiced'];

    /** Who moves the order out of each status: buyer side or supplier side. */
    public const NEXT = [
        'draft' => ['to' => 'issued', 'side' => 'buyer', 'label' => 'Issue to supplier', 'stamp' => 'issued_at'],
        'issued' => ['to' => 'acknowledged', 'side' => 'supplier', 'label' => 'Acknowledge order', 'stamp' => 'acknowledged_at'],
        'acknowledged' => ['to' => 'in_transit', 'side' => 'supplier', 'label' => 'Mark as in transit', 'stamp' => 'shipped_at'],
        'in_transit' => ['to' => 'delivered', 'side' => 'buyer', 'label' => 'Confirm delivery', 'stamp' => 'delivered_at'],
        'delivered' => ['to' => 'invoiced', 'side' => 'supplier', 'label' => 'Mark as invoiced', 'stamp' => 'invoiced_at'],
    ];

    /** Database defaults, mirrored so a fresh model has them before it is reloaded. */
    protected $attributes = ['status' => 'draft', 'currency' => 'USD', 'total_cents' => 0];

    protected $fillable = ['number', 'rfq_id', 'supplier_id', 'quote_id', 'buyer_id', 'status', 'currency', 'total_cents', 'payment_terms', 'delivery_location', 'expected_at', 'award_justification', 'issued_at', 'acknowledged_at', 'shipped_at', 'delivered_at', 'invoiced_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['expected_at' => 'date', 'issued_at' => 'datetime', 'acknowledged_at' => 'datetime', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime', 'invoiced_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return match (true) {
            $user->role === 'admin' => $q,
            $user->role === 'buyer' => $q->where('buyer_id', $user->id),
            $user->role === 'supplier' && $user->supplier_id !== null => $q->where('supplier_id', $user->supplier_id)->where('status', '!=', 'draft'),
            default => $q->whereRaw('1 = 0'),
        };
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
