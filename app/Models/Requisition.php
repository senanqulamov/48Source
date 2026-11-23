<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisition extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Awaiting approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'converted' => 'Converted to RFQ', 'cancelled' => 'Cancelled'];

    protected $fillable = ['number', 'requester_id', 'category_id', 'rfq_id', 'title', 'justification', 'needed_by', 'currency', 'total_cents', 'status', 'submitted_at', 'decided_at'];
    protected $attributes = ['status' => 'draft', 'currency' => 'USD', 'total_cents' => 0, 'rfq_id' => null];

    protected function casts(): array
    {
        return ['needed_by' => 'date', 'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'total_cents' => 'integer'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequisitionApproval::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /** Requesters see their own; approvers and administrators see what they may decide. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return match (true) {
            $user->role === 'admin' => $q,
            $user->allowed('requisitions', 'A') => $q->where(fn ($w) => $w->where('requester_id', $user->id)->orWhere('status', '!=', 'draft')),
            $user->allowed('requisitions') => $q->where('requester_id', $user->id),
            default => $q->whereRaw('1 = 0'),
        };
    }
}
