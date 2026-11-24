<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Organisation-wide values kept in the settings table. Reads are cached for the request. */
final class Setting
{
    /** The SLA rules and their limits: key => [label, default, min, max]. */
    public const SLA = [
        'sla.quote_reminder_days' => ['First reminder (days before deadline)', 1, 1, 14],
        'sla.award_days' => ['Award target (days after closing)', 5, 1, 60],
        'sla.po_ack_days' => ['Purchase order acknowledgement (days)', 3, 1, 30],
        'sla.requisition_days' => ['Requisition approval (days)', 2, 1, 30],
    ];

    /** @var array<string, string>|null */
    private static ?array $all = null;

    private static function all(): array
    {
        if (self::$all === null) {
            try {
                self::$all = DB::table('settings')->pluck('value', 'key')->all();
            } catch (\Throwable) {
                self::$all = [];
            }
        }

        return self::$all;
    }

    public static function int(string $key, ?int $default = null): int
    {
        $default ??= self::SLA[$key][1] ?? 0;
        $value = self::all()[$key] ?? null;

        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }

    public static function set(string $key, string|int $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()]);
        self::$all = null;
    }

    public static function forget(): void
    {
        self::$all = null;
    }
}
