<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisitionItem extends Model
{
    protected $fillable = ['requisition_id', 'name', 'quantity_milli', 'unit', 'est_price_cents', 'total_cents'];
    protected $attributes = ['est_price_cents' => null, 'total_cents' => 0];

    protected function casts(): array
    {
        return ['quantity_milli' => 'integer', 'est_price_cents' => 'integer', 'total_cents' => 'integer'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }
}
