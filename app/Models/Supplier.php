<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending' => 'Pending approval', 'active' => 'Active', 'inactive' => 'Inactive', 'blocked' => 'Blocked'];

    /** Allowed lifecycle moves: from => [to, ...]. */
    public const TRANSITIONS = ['pending' => ['active', 'blocked'], 'active' => ['inactive', 'blocked'], 'inactive' => ['active', 'blocked'], 'blocked' => ['active']];

    /** Database defaults, mirrored so a fresh model has them before it is reloaded. */
    protected $attributes = ['status' => 'pending', 'rating_tenths' => 40];

    protected $fillable = ['name', 'legal_name', 'category_id', 'city', 'country', 'contact_name', 'email', 'phone', 'status', 'status_reason', 'rating_tenths', 'approved_at', 'approved_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(RfqInvitation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function rating(): string
    {
        return number_format($this->rating_tenths / 10, 1);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(SupplierScorecard::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SupplierAction::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(FieldAssessment::class);
    }
}
