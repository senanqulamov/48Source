<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory;

    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Submitted', 'withdrawn' => 'Withdrawn', 'won' => 'Won', 'lost' => 'Not selected'];

    /** Database defaults, mirrored so a fresh model has them before it is reloaded. */
    protected $attributes = ['original_total_cents' => null, 'adjust_reason' => null, 'adjusted_by' => null, 'adjusted_at' => null, 'status' => 'draft', 'currency' => 'USD', 'total_cents' => 0, 'revision' => 0];

    protected $fillable = ['rfq_id', 'supplier_id', 'submitted_by', 'status', 'currency', 'delivery_days', 'payment_terms', 'valid_until', 'notes', 'total_cents', 'revision', 'submitted_at', 'original_total_cents', 'adjust_reason', 'adjusted_by', 'adjusted_at'];

    protected function casts(): array
    {
        return ['adjusted_at' => 'datetime', 'original_total_cents' => 'integer', 'valid_until' => 'date', 'submitted_at' => 'datetime'];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->endOfDay()->isPast();
    }

    /** Number of request items this quotation prices. */
    public function coverage(): int
    {
        return $this->items->whereNotNull('unit_price_cents')->count();
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
