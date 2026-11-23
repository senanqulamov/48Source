<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sequence extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'year', 'value'];

    /** Next document number, e.g. RFQ-2026-0042. Safe under concurrent requests. */
    public static function next(string $name): string
    {
        return DB::transaction(function () use ($name) {
            $year = (int) now()->format('Y');
            self::firstOrCreate(['name' => $name, 'year' => $year]);
            $row = self::where(['name' => $name, 'year' => $year])->lockForUpdate()->first();
            $row->increment('value');

            return sprintf('%s-%d-%04d', $name, $year, $row->value);
        });
    }
}
