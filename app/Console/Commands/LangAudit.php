<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Nav;
use App\Support\Translator;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class LangAudit extends Command
{
    protected $signature = 'lang:audit {locale : az or ru} {--strict : Return failure when untranslated interface text is found}';

    protected $description = 'Render each role menu page and list untranslated interface-looking text.';

    public function handle(Kernel $kernel): int
    {
        $locale = (string) $this->argument('locale');
        if (! in_array($locale, ['az', 'ru'], true)) {
            $this->error('Locale must be az or ru.');

            return self::FAILURE;
        }
        $all = [];
        $userData = $this->userData();
        foreach (['admin', 'buyer', 'supplier', 'seller', 'market_worker', 'supplier_worker'] as $role) {
            $user = User::where('role', $role)->where('status', 'active')->whereNotNull('email_verified_at')->first();
            if (! $user) {
                $this->warn("No active verified {$role} account - skipped.");
                continue;
            }
            $user->forceFill(['locale' => $locale]);
            Auth::setUser($user);
            foreach (collect(Nav::for($user))->flatMap(fn ($group) => collect($group[1])->pluck('url'))->push(route('settings'), route('notifications.index'))->unique() as $url) {
                $request = Request::create($url, 'GET');
                $request->setUserResolver(fn () => $user);
                $response = $kernel->handle($request);
                if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                    continue;
                }
                foreach (Translator::untranslated((string) $response->getContent(), $locale) as $text) {
                    if (isset($userData[$text])) {
                        continue;
                    }
                    $all[$role][parse_url($url, PHP_URL_PATH) ?: '/'][$text] = true;
                }
            }
            Auth::logout();
        }
        if (! $all) {
            $this->info("No untranslated interface text found for {$locale}.");

            return self::SUCCESS;
        }
        foreach ($all as $role => $pages) {
            foreach ($pages as $page => $texts) {
                foreach (array_keys($texts) as $text) {
                    $this->line("[{$role}] {$page}: {$text}");
                }
            }
        }
        $this->warn('A native Azerbaijani and Russian wording review is still required.');

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<string, true> Values entered or generated as records, never interface copy. */
    private function userData(): array
    {
        $values = [];
        foreach ([
            ['users', ['name', 'department']], ['suppliers', ['name', 'legal_name', 'city', 'contact_name']],
            ['categories', ['name']], ['rfqs', ['title']], ['rfq_items', ['name', 'description']],
            ['purchase_orders', ['award_justification']], ['requisitions', ['title', 'description']],
            ['contracts', ['title', 'description']], ['rfis', ['title', 'description']],
            ['field_assessments', ['title', 'findings', 'decision_note']], ['products', ['name', 'description']],
            ['markets', ['name']], ['stores', ['name']], ['activity_logs', ['actor_name', 'action', 'subject_label']],
            ['supplier_documents', ['title']], ['supplier_actions', ['title', 'detail']], ['conversations', ['subject']],
        ] as [$table, $columns]) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                foreach (DB::table($table)->whereNotNull($column)->distinct()->pluck($column) as $value) {
                    if (is_string($value) && $value !== '') {
                        $values[trim($value)] = true;
                    }
                }
            }
        }
        foreach (['Ctrl K', 'Ctrl Enter', 'Enter', 'Esc', 'AZ', 'Excel', 'Excel (.xlsx)', 'CSV', 'RFQ', 'RFI', 'SKU'] as $technical) {
            $values[$technical] = true;
        }

        return $values;
    }
}
