<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BafoRound extends Model
{
    protected $fillable = ['rfq_id', 'round', 'opened_by', 'status', 'deadline_at', 'message', 'closed_at'];
    protected $attributes = ['status' => 'open', 'message' => null, 'closed_at' => null];

    protected function casts(): array
    {
        return ['deadline_at' => 'datetime', 'closed_at' => 'datetime', 'round' => 'integer'];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->deadline_at->isFuture();
    }
}
