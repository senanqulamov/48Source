<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfi extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed'];

    protected $fillable = ['number', 'buyer_id', 'rfq_id', 'title', 'description', 'questions', 'status', 'deadline_at'];
    protected $attributes = ['status' => 'draft', 'rfq_id' => null, 'description' => null, 'deadline_at' => null];

    protected function casts(): array
    {
        return ['questions' => 'array', 'deadline_at' => 'datetime'];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RfiResponse::class);
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
            $user->role === 'buyer' => $q->where('buyer_id', $user->id),
            $user->role === 'supplier' && $user->supplier_id !== null => $q->where('status', '!=', 'draft')->whereHas('responses', fn ($r) => $r->where('supplier_id', $user->supplier_id)),
            default => $q->whereRaw('1 = 0'),
        };
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->deadline_at !== null && $this->deadline_at->isFuture();
    }
}
