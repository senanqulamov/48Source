<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketWorkerTask extends Model
{
    public const TYPES = ['order', 'availability', 'price'];

    protected $fillable = ['market_id', 'seller_id', 'worker_id', 'title', 'details', 'type', 'due_at', 'completed_at'];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function market(): BelongsTo { return $this->belongsTo(Market::class); }

    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }

    public function worker(): BelongsTo { return $this->belongsTo(User::class, 'worker_id'); }
}
