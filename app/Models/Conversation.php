<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['supplier_id', 'created_by', 'subject', 'subject_ref_type', 'subject_ref_id', 'last_message_at'];
    protected $attributes = ['subject_ref_type' => null, 'subject_ref_id' => null, 'last_message_at' => null];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ConversationRead::class);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return match (true) {
            $user->isSourcing() => $q,
            $user->role === 'supplier' && $user->supplier_id !== null => $q->where('supplier_id', $user->supplier_id),
            default => $q->whereRaw('1 = 0'),
        };
    }
}
