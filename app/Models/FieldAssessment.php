<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldAssessment extends Model
{
    public const STATUSES = ['assigned' => 'Assigned', 'in_progress' => 'In progress', 'submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public const DEFAULT_CHECKLIST = ['Site access and unloading area are suitable', 'Storage on site is available and safe', 'Quantities on the request match the site', 'Safety rules of the site are understood', 'Delivery window agreed with the site contact'];

    protected $fillable = ['number', 'supplier_id', 'rfq_id', 'requested_by', 'assigned_to', 'title', 'checklist', 'answers', 'findings', 'status', 'due_on', 'submitted_at', 'decided_by', 'decision_note', 'decided_at'];
    protected $attributes = ['status' => 'assigned', 'answers' => null, 'findings' => null, 'rfq_id' => null, 'assigned_to' => null, 'due_on' => null, 'submitted_at' => null, 'decided_by' => null, 'decision_note' => null, 'decided_at' => null];

    protected function casts(): array
    {
        return ['checklist' => 'array', 'answers' => 'array', 'due_on' => 'date', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
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
            in_array($user->role, ['supplier', 'supplier_worker'], true) && $user->supplier_id !== null => $q->where('supplier_id', $user->supplier_id),
            default => $q->whereRaw('1 = 0'),
        };
    }
}
