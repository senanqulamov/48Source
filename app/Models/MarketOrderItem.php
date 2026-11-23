<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketOrderItem extends Model
{
    protected $fillable = ['market_order_id', 'offer_id', 'product_id', 'rfq_item_id', 'name', 'sku', 'unit', 'quantity_milli', 'unit_price_cents', 'total_cents'];
    protected $attributes = ['rfq_item_id' => null];

    protected function casts(): array
    {
        return ['quantity_milli' => 'integer', 'unit_price_cents' => 'integer', 'total_cents' => 'integer'];
    }
}
