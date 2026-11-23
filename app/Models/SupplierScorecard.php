<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierScorecard extends Model
{
    protected $fillable = ['supplier_id', 'period', 'quality', 'delivery', 'price', 'service', 'score_tenths', 'comment', 'created_by'];
    protected $attributes = ['comment' => null];

    protected function casts(): array
    {
        return ['quality' => 'integer', 'delivery' => 'integer', 'price' => 'integer', 'service' => 'integer', 'score_tenths' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
