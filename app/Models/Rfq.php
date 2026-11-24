<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rfq extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed', 'awarded' => 'Awarded', 'cancelled' => 'Cancelled'];

    public const TYPES = ['invited' => 'Invited suppliers only', 'public' => 'Public tender'];

    /** Database defaults, mirrored so a fresh model has them before it is reloaded. */
    protected $attributes = ['requires_assessment' => false, 'last_reminded_at' => null, 'type' => 'invited', 'status' => 'draft', 'currency' => 'USD', 'scoring_preset' => 'balanced', 'sealed' => false, 'round' => 1];

    protected $fillable = ['number', 'title', 'description', 'type', 'category_id', 'buyer_id', 'status', 'currency', 'budget_cents', 'deadline_at', 'delivery_location', 'required_by', 'requirements', 'payment_terms', 'scoring_preset', 'sealed', 'round', 'published_at', 'closed_at', 'awarded_at', 'cancelled_at', 'cancel_reason', 'last_reminded_at', 'requires_assessment'];

    protected function casts(): array
    {
        return ['requires_assessment' => 'boolean', 'last_reminded_at' => 'datetime', 'deadline_at' => 'datetime', 'required_by' => 'date', 'published_at' => 'datetime', 'closed_at' => 'datetime', 'awarded_at' => 'datetime', 'cancelled_at' => 'datetime', 'sealed' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class)->orderBy('position');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(RfqInvitation::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'rfq_invitations')->withPivot('status', 'decline_reason', 'responded_at')->withTimestamps();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** Quotations a buyer may see and compare. */
    public function liveQuotes(): HasMany
    {
        return $this->quotes()->whereIn('status', ['submitted', 'won', 'lost']);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isOpenForQuotes(): bool
    {
        return $this->status === 'open' && $this->deadline_at && $this->deadline_at->isFuture();
    }

    /** Sealed requests keep prices hidden from the buyer side until quoting ends. */
    public function pricesHidden(): bool
    {
        return $this->sealed && $this->status === 'open';
    }

    /** Requests the user is allowed to see at all. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return match (true) {
            $user->role === 'admin' => $q,
            $user->role === 'buyer' => $q->where('buyer_id', $user->id),
            in_array($user->role, ['supplier', 'supplier_worker'], true) && $user->supplier_id !== null => $q->where('status', '!=', 'draft')->where(fn (Builder $w) => $w
                ->whereHas('invitations', fn (Builder $i) => $i->where('supplier_id', $user->supplier_id))
                ->orWhere(fn (Builder $p) => $p->where('type', 'public')->where('status', 'open'))),
            default => $q->whereRaw('1 = 0'),
        };
    }

    public function questions(): HasMany
    {
        return $this->hasMany(RfqQuestion::class);
    }

    public function bafoRounds(): HasMany
    {
        return $this->hasMany(BafoRound::class);
    }

    public function auction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Auction::class);
    }

    /** A best-and-final round that is open right now, if any. */
    public function openBafo(): ?BafoRound
    {
        return $this->bafoRounds()->where('status', 'open')->where('deadline_at', '>', now())->latest('round')->first();
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
