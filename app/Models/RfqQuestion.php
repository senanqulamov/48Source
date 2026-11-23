<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqQuestion extends Model
{
    protected $fillable = ['rfq_id', 'supplier_id', 'asked_by', 'question', 'answer', 'answered_by', 'answered_at'];
    protected $attributes = ['answer' => null, 'answered_by' => null, 'answered_at' => null];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
