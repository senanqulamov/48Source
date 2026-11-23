<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    /** Database defaults, mirrored so a fresh model has them before it is reloaded. */
    protected $attributes = ['total_cents' => 0];

    protected $fillable = ['quote_id', 'rfq_item_id', 'unit_price_cents', 'total_cents'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class);
    }
}
