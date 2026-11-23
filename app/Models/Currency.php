<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'active'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** Codes that can be used on new requests, quotations and contracts. @return array<int, string> */
    public static function codes(): array
    {
        static $has = null;
        $has ??= Schema::hasTable('currencies');
        $codes = $has ? self::where('active', true)->orderBy('code')->pluck('code')->all() : [];

        return $codes ?: ['USD'];
    }
}
