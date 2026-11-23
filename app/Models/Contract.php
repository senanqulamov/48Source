<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'active' => 'Active', 'expired' => 'Expired', 'terminated' => 'Terminated'];

    protected $fillable = ['number', 'supplier_id', 'owner_id', 'rfq_id', 'title', 'status', 'currency', 'ceiling_cents', 'released_cents', 'starts_on', 'ends_on', 'payment_terms', 'terms', 'end_reason', 'purchase_order_id'];
    protected $attributes = ['status' => 'draft', 'currency' => 'USD', 'released_cents' => 0, 'ceiling_cents' => null, 'rfq_id' => null, 'end_reason' => null, 'purchase_order_id' => null];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'ceiling_cents' => 'integer', 'released_cents' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(ContractRelease::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return match (true) {
            $user->isSourcing() => $q,
            $user->role === 'supplier' && $user->supplier_id !== null => $q->where('supplier_id', $user->supplier_id)->where('status', '!=', 'draft'),
            default => $q->whereRaw('1 = 0'),
        };
    }

    /** Value still free under the ceiling; null when the contract has no ceiling. */
    public function remainingCents(): ?int
    {
        return $this->ceiling_cents === null ? null : max(0, $this->ceiling_cents - $this->released_cents);
    }
}
