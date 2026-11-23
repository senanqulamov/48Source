<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    protected $fillable = ['name', 'city', 'address', 'status'];
    protected $attributes = ['status' => 'active', 'city' => null, 'address' => null];

    protected function casts(): array
    {
        return [];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
