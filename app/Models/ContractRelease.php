<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractRelease extends Model
{
    public const STATUSES = ['issued' => 'Issued', 'accepted' => 'Accepted', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

    protected $fillable = ['number', 'contract_id', 'created_by', 'status', 'total_cents', 'needed_by', 'note', 'accepted_at', 'delivered_at'];
    protected $attributes = ['status' => 'issued', 'total_cents' => 0];

    protected function casts(): array
    {
        return ['needed_by' => 'date', 'accepted_at' => 'datetime', 'delivered_at' => 'datetime', 'total_cents' => 'integer'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractReleaseItem::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
