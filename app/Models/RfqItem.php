<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqItem extends Model
{
    use HasFactory;

    /** Database defaults, mirrored so a fresh model has them before it is reloaded. */
    protected $attributes = ['position' => 1, 'unit' => 'pcs'];

    protected $fillable = ['rfq_id', 'position', 'name', 'description', 'quantity_milli', 'unit', 'target_price_cents'];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function qty(): string
    {
        return Money::qty($this->quantity_milli);
    }
}
