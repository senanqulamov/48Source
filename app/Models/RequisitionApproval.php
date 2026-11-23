<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisitionApproval extends Model
{
    protected $fillable = ['requisition_id', 'step', 'role', 'label', 'status', 'approver_id', 'comment', 'decided_at'];
    protected $attributes = ['status' => 'waiting', 'approver_id' => null, 'comment' => null, 'decided_at' => null];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'step' => 'integer'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
