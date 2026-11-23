<?php

namespace App\Services;

use App\Exceptions\BusinessRule;
use App\Models\Category;
use App\Models\Supplier;
use App\Support\Audit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Health checks for the system page, and the supplier import. */
class SystemService
{
    /** @return array<int, array{name:string,ok:bool,note:string}> */
    public function health(): array
    {
        $check = function (string $name, callable $fn): array {
            try {
                $note = $fn();

                return ['name' => $name, 'ok' => true, 'note' => (string) $note];
            } catch (Throwable $e) {
                return ['name' => $name, 'ok' => false, 'note' => mb_strimwidth($e->getMessage(), 0, 160, '...')];
            }
        };
        $beat = Cache::get('scheduler:heartbeat');

        return [
            $check('Database', fn () => 'Connected (' . DB::connection()->getDriverName() . '), ' . count(DB::select('select 1')) . ' test query answered'),
            $check('Cache', function () {
                Cache::put('health:probe', 'ok', 30);
                if (Cache::get('health:probe') !== 'ok') {
                    throw new \RuntimeException('A value written to the cache could not be read back.');
                }

                return 'Read and write work (' . config('cache.default') . ')';
            }),
            $check('File storage', function () {
                Storage::disk('local')->put('health-probe.txt', 'ok');
                Storage::disk('local')->delete('health-probe.txt');

                return 'The private disk can be written';
            }),
            ['name' => 'Email', 'ok' => config('mail.default') !== 'log' && config('mail.default') !== 'array', 'note' => config('mail.default') === 'log' ? 'Mail is written to the log file and not delivered. Set MAIL_MAILER in .env.' : 'Mailer: ' . config('mail.default')],
            ['name' => 'Scheduler', 'ok' => $beat !== null && now()->diffInMinutes($beat, true) <= 10, 'note' => $beat ? 'Last ran ' . \Illuminate\Support\Carbon::parse($beat)->diffForHumans() : 'Has not run yet. Add the cron entry for "php artisan schedule:run", or run "php artisan schedule:work".'],
            ['name' => 'Debug mode', 'ok' => ! config('app.debug') || app()->environment('local', 'testing'), 'note' => config('app.debug') ? 'APP_DEBUG is on. Switch it off in production.' : 'Off'],
        ];
    }

    /**
     * Import suppliers. First row: name, email, city, contact, phone, category. New suppliers wait for approval.
     * A supplier whose name already exists is skipped.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{saved:int,errors:array<int,string>}
     */
    public function importSuppliers(array $rows): array
    {
        $head = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($rows) ?? []);
        if (! in_array('name', $head, true)) {
            throw new BusinessRule('The file needs at least the column "name" in its first row.');
        }
        $saved = 0;
        $errors = [];
        foreach ($rows as $i => $cells) {
            $row = [];
            foreach ($head as $c => $name) {
                $row[$name] = is_scalar($cells[$c] ?? null) ? trim((string) $cells[$c]) : '';
            }
            if (implode('', $row) === '') {
                continue;
            }
            $line = 'Row ' . ($i + 2) . ': ';
            if (($row['name'] ?? '') === '') {
                $errors[] = $line . 'the name is empty.';
            } elseif (Supplier::where('name', $row['name'])->exists()) {
                $errors[] = $line . "\"{$row['name']}\" already exists.";
            } elseif (($row['email'] ?? '') !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = $line . "\"{$row['email']}\" is not an email address.";
            } else {
                Supplier::create(['name' => $row['name'], 'email' => ($row['email'] ?? '') ?: null, 'city' => ($row['city'] ?? '') ?: null, 'contact_name' => ($row['contact'] ?? '') ?: null, 'phone' => ($row['phone'] ?? '') ?: null,
                    'category_id' => ($row['category'] ?? '') !== '' ? Category::where('name', $row['category'])->value('id') : null, 'status' => 'pending']);
                $saved++;
            }
            if (count($errors) >= 50) {
                break;
            }
        }
        Audit::log('Import', "Imported suppliers: {$saved} saved, " . count($errors) . ' skipped');

        return ['saved' => $saved, 'errors' => $errors];
    }
}
