<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $fillable = ['product_id', 'store_id', 'price_cents', 'currency', 'moq_milli', 'stock_milli', 'lead_days', 'status'];
    protected $attributes = ['status' => 'active', 'currency' => 'USD', 'moq_milli' => 1000, 'stock_milli' => 0, 'lead_days' => 1];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'moq_milli' => 'integer', 'stock_milli' => 'integer', 'lead_days' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Offers a buyer can order from right now. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('offers.status', 'active')->where('stock_milli', '>', 0)->whereHas('store', fn ($s) => $s->where('status', 'active'))->whereHas('product', fn ($p) => $p->where('status', 'active'));
    }
}
