<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id', 'request_item_id', 'description',
        'quantity', 'unit_price', 'tax_rate', 'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPurchaseOrder::class, 'purchase_order_id');
    }
}
