<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementPurchaseOrder extends Model
{
    protected $fillable = [
        'request_id', 'quote_id', 'buyer_id', 'supplier_id', 'awarded_by',
        'award_justification', 'currency', 'total_amount', 'status',
        'issued_at', 'acknowledged_at', 'in_transit_at', 'delivered_at', 'invoiced_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'delivered_at' => 'datetime',
        'invoiced_at' => 'datetime',
    ];

    public const TRANSITIONS = [
        'draft' => 'issued',
        'issued' => 'acknowledged',
        'acknowledged' => 'in_transit',
        'in_transit' => 'delivered',
        'delivered' => 'invoiced',
    ];

    public function request(): BelongsTo { return $this->belongsTo(Request::class); }
    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(User::class, 'supplier_id'); }
    public function awarder(): BelongsTo { return $this->belongsTo(User::class, 'awarded_by'); }
    public function lines(): HasMany { return $this->hasMany(ProcurementPurchaseOrderLine::class, 'purchase_order_id'); }

    public function nextStatus(): ?string
    {
        return self::TRANSITIONS[$this->status] ?? null;
    }
}
