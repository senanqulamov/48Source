<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkerTask extends Model
{
    protected $fillable = ['market_order_id', 'store_id', 'type', 'status', 'assigned_to', 'done_by', 'done_at'];
    protected $attributes = ['status' => 'open', 'assigned_to' => null, 'done_by' => null, 'done_at' => null];

    protected function casts(): array
    {
        return ['done_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketOrder::class, 'market_order_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function doer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
