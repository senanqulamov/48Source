<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'purchase_order_item_id', 'quantity_milli', 'unit_price_cents', 'total_cents'];

    protected function casts(): array
    {
        return ['quantity_milli' => 'integer', 'unit_price_cents' => 'integer', 'total_cents' => 'integer'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
