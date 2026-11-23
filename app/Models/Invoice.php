<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUSES = ['submitted' => 'Submitted', 'matched' => 'Matched', 'disputed' => 'Does not match', 'approved' => 'Approved for payment', 'paid' => 'Paid', 'rejected' => 'Rejected'];

    protected $fillable = ['number', 'supplier_ref', 'purchase_order_id', 'supplier_id', 'submitted_by', 'status', 'currency', 'total_cents', 'issued_on', 'due_on', 'issues', 'decision_note', 'decided_by', 'decided_at', 'paid_at', 'exported_at'];
    protected $attributes = ['status' => 'submitted', 'currency' => 'USD', 'total_cents' => 0, 'issues' => null, 'decision_note' => null, 'decided_by' => null, 'decided_at' => null, 'paid_at' => null, 'exported_at' => null, 'due_on' => null];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'due_on' => 'date', 'issues' => 'array', 'decided_at' => 'datetime', 'paid_at' => 'datetime', 'exported_at' => 'datetime', 'total_cents' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
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
            $user->role === 'admin' => $q,
            $user->role === 'buyer' => $q->whereHas('order', fn ($o) => $o->where('buyer_id', $user->id)),
            $user->role === 'supplier' && $user->supplier_id !== null => $q->where('supplier_id', $user->supplier_id),
            default => $q->whereRaw('1 = 0'),
        };
    }
}
