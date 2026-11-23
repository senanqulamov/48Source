<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    protected $fillable = ['rfq_id', 'opened_by', 'status', 'starts_at', 'ends_at', 'min_step_bp', 'extend_minutes', 'applied_at'];
    protected $attributes = ['status' => 'live', 'min_step_bp' => 50, 'extend_minutes' => 2, 'applied_at' => null];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'applied_at' => 'datetime', 'min_step_bp' => 'integer', 'extend_minutes' => 'integer'];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'live' && $this->ends_at->isFuture();
    }
}
