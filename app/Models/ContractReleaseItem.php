<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractReleaseItem extends Model
{
    protected $fillable = ['contract_release_id', 'contract_item_id', 'name', 'unit', 'quantity_milli', 'unit_price_cents', 'total_cents'];

    protected function casts(): array
    {
        return ['quantity_milli' => 'integer', 'unit_price_cents' => 'integer', 'total_cents' => 'integer'];
    }
}
