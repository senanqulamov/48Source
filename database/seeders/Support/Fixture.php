<?php

namespace Database\Seeders\Support;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class Fixture
{
    public const BATCH = 'full-workflows-v2';

    private static ?string $password = null;

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now('Asia/Baku')->subDay()->startOfDay();
    }

    public static function password(): string
    {
        return self::$password ??= (string) (env('WORKFLOW_SEED_PASSWORD') ?: Str::password(22, symbols: false));
    }

    public static function passwordHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(self::password());
    }

    public static function insert(string $table, array $values, bool $track = false): int
    {
        $id = DB::table($table)->insertGetId($values);
        if ($track) {
            DB::table('workflow_fixture_records')->insert([
                'table_name' => $table, 'record_id' => $id,
                'batch' => self::BATCH, 'created_at' => now(),
            ]);
        }

        return $id;
    }

    public static function stamped(array $values, CarbonImmutable|string|null $date = null): array
    {
        $at = $date instanceof CarbonImmutable ? $date->toDateTimeString() : ($date ?? now()->toDateTimeString());

        return array_merge(['created_at' => $at, 'updated_at' => $at], $values);
    }

    public static function qtyTotal(int $priceCents, int $quantityMilli): int
    {
        return Money::lineTotal($priceCents, $quantityMilli);
    }

    public static function note(string $section, string $message): void
    {
        if (app()->runningInConsole()) {
            fwrite(STDOUT, sprintf("  [48Source / %-18s] %s\n", $section, $message));
        }
    }
}
