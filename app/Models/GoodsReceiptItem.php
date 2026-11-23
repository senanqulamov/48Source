<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptItem extends Model
{
    protected $fillable = ['goods_receipt_id', 'purchase_order_item_id', 'accepted_milli', 'rejected_milli', 'note'];
    protected $attributes = ['rejected_milli' => 0, 'note' => null];

    protected function casts(): array
    {
        return ['accepted_milli' => 'integer', 'rejected_milli' => 'integer'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
