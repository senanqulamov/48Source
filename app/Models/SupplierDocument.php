<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierDocument extends Model
{
    public const TYPES = ['registration' => 'Company registration', 'tax' => 'Tax certificate', 'insurance' => 'Insurance certificate', 'iso' => 'Quality certificate', 'bank' => 'Bank details letter', 'other' => 'Other'];

    protected $fillable = ['supplier_id', 'type', 'title', 'path', 'original_name', 'expires_on', 'status', 'uploaded_by', 'verified_by'];
    protected $attributes = ['status' => 'pending', 'path' => null, 'original_name' => null, 'expires_on' => null, 'verified_by' => null];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast() && ! $this->expires_on->isToday();
    }

    public function expiresSoon(): bool
    {
        return $this->expires_on !== null && ! $this->isExpired() && $this->expires_on->lte(now()->addDays(30));
    }
}
