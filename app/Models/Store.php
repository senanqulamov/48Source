<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = ['market_id', 'name', 'stall', 'phone', 'status'];
    protected $attributes = ['status' => 'active', 'stall' => null, 'phone' => null];

    protected function casts(): array
    {
        return [];
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketOrder::class);
    }
}
