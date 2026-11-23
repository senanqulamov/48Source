<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfiResponse extends Model
{
    protected $fillable = ['rfi_id', 'supplier_id', 'status', 'answers', 'answered_by', 'answered_at'];
    protected $attributes = ['status' => 'invited', 'answers' => null, 'answered_by' => null, 'answered_at' => null];

    protected function casts(): array
    {
        return ['answers' => 'array', 'answered_at' => 'datetime'];
    }

    public function rfi(): BelongsTo
    {
        return $this->belongsTo(Rfi::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
