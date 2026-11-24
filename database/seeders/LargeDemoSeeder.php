<?php

namespace Database\Seeders;

use App\Models\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fast, invented fixture data for exercising lists and reports.  It deliberately
 * uses DB inserts for volume, while DemoSeeder remains the service-level example.
 */
class LargeDemoSeeder extends Seeder
{
    private const PREFIX = 'Demo ';
    /** Invented Azerbaijani business vocabulary. These are combinations, never numbered display names. */
    private const CITIES = ['Bakı', 'Sumqayıt', 'Gəncə', 'Mingəçevir', 'Şirvan', 'Lənkəran'];
    private const PEOPLE_GIVEN = ['Elvin', 'Aysel', 'Nigar', 'Tural', 'Leyla', 'Emin', 'Rauf', 'Sevinc', 'Orxan', 'Günel', 'Kamran', 'Zəhra', 'Ramil', 'Aynur', 'Cavid', 'Səbinə'];
    private const PEOPLE_FAMILY = ['Məmmədov', 'Quliyeva', 'Rzayeva', 'Həsənov', 'Hüseynova', 'Cəfərov', 'Əliyev', 'İsmayılova', 'Abbasov', 'Babayev', 'Kərimova', 'Sultanov', 'Nəcəfli', 'Vəliyeva', 'Qasımov', 'Mirzəyeva'];
    private const COMPANY_PREFIX = ['Abşeron', 'Qobustan', 'Şirvan', 'Xəzər', 'Zirvə', 'Araz', 'Buta', 'Gilan', 'Kür', 'Muğan'];
    private const COMPANY_TRADE = ['Polad', 'Sement və Çınqıl', 'Elektrik Sistemləri', 'Sənaye Avadanlığı', 'Tikinti Təchizatı', 'Logistika Xidmətləri', 'Təhlükəsizlik Sistemləri', 'Texniki Həllər', 'İqlim Sistemləri', 'Ofis Təchizatı'];
    private const COMPANY_QUALIFIER = ['Təminat', 'Layihə', 'İstehsal', 'Ticarət', 'Servis', 'İnşaat', 'Mühəndislik', 'Resurs', 'Sənaye', 'Təchizat'];
    private const CATEGORY_TREE = [
        'Tikinti materialları' => ['Armatur və metal məmulatlar', 'Sement və quru qarışıqlar', 'Beton və aqreqatlar', 'Kərpic və bloklar', 'İzolyasiya materialları'],
        'Sənaye avadanlığı' => ['Nasoslar və klapanlar', 'Generatorlar və kompressorlar', 'Elektrik mühərrikləri', 'Ölçmə cihazları', 'Ehtiyat hissələri'],
        'Elektrik və avtomatlaşdırma' => ['Kabel və naqillər', 'Paylayıcı lövhələr', 'İşıqlandırma', 'Avtomatlaşdırma və PLC', 'Akkumulyator və UPS'],
        'İT və rabitə' => ['Noutbuk və iş stansiyaları', 'Şəbəkə avadanlığı', 'Server və saxlama', 'Ofis periferiyası', 'Proqram lisenziyaları'],
        'Əməyin mühafizəsi' => ['Fərdi mühafizə vasitələri', 'Yanğın təhlükəsizliyi', 'İşarələr və baryerlər', 'İlk yardım və gigiyena', 'Nəzarət sistemləri'],
        'Nəqliyyat və logistika' => ['Yükdaşıma', 'Xüsusi texnika icarəsi', 'Gömrük və ekspeditor', 'Anbar xidmətləri', 'Qablaşdırma'],
        'İstismar xidmətləri' => ['HVAC xidməti', 'Təmizlik xidməti', 'Mühafizə xidməti', 'Texniki xidmət', 'Landşaft və abadlıq'],
        'Peşəkar xidmətlər' => ['Layihələndirmə', 'Geodeziya və laboratoriya', 'Hüquq və audit', 'Təlim və sertifikatlaşdırma', 'Sığorta xidmətləri'],
    ];
    private string $batch = '';

    private function person(int $n): string
    {
        return self::PEOPLE_GIVEN[$n % count(self::PEOPLE_GIVEN)].' '.self::PEOPLE_FAMILY[intdiv($n, count(self::PEOPLE_GIVEN)) % count(self::PEOPLE_FAMILY)];
    }

    private function company(int $n): string
    {
        $prefix = self::COMPANY_PREFIX[$n % count(self::COMPANY_PREFIX)];
        $trade = self::COMPANY_TRADE[intdiv($n, count(self::COMPANY_PREFIX)) % count(self::COMPANY_TRADE)];
        $qualifier = self::COMPANY_QUALIFIER[intdiv($n, count(self::COMPANY_PREFIX) * count(self::COMPANY_TRADE)) % count(self::COMPANY_QUALIFIER)];

        return "{$prefix} {$trade} {$qualifier} ".($n % 2 ? 'MMC' : 'ASC');
    }

    private function project(int $n): string
    {
        return ['Yasamal yaşayış kompleksi, B bloku', 'Hövsan logistika mərkəzi', 'Sumqayıt istehsalat sahəsi', 'Gəncə regional anbarı', 'Mingəçevir texniki parkı', 'Şirvan su təsərrüfatı sahəsi'][$n % 6];
    }

    private function marketName(int $n): string
    {
        $styles = ['Mərkəzi', 'Şərq', 'Qərb', 'Sahil', 'Dağətəyi', 'Sənaye'];

        return self::CITIES[$n % count(self::CITIES)].' '.$styles[intdiv($n, count(self::CITIES)) % count($styles)].' Təchizat Bazarı';
    }

    /** @return array{name:string,unit:string,price:int} */
    private function catalogueItem(int $n): array
    {
        $items = [
            ['Armatur A500C 16 mm', 't', 148000], ['Portland sementi M400', 'bag', 760], ['Qaynaq toru 150 x 150 mm', 'sheet', 3900], ['Beton C25/30', 'm3', 14500],
            ['Sənaye su nasosu 7.5 kW', 'pcs', 185000], ['Flanşlı klapan DN100', 'pcs', 24000], ['Mis kabel 3 x 2.5 mm²', 'm', 320], ['LED sənaye projektoru 150 W', 'pcs', 21500],
            ['Noutbuk i7, 16 GB RAM', 'pcs', 245000], ['24 portlu şəbəkə kommutatoru', 'pcs', 98000], ['Qoruyucu dəbilqə', 'pcs', 1900], ['Yükdaşıma xidməti, Bakı', 'trip', 65000],
        ];

        return ['name' => $items[$n % count($items)][0], 'unit' => $items[$n % count($items)][1], 'price' => $items[$n % count($items)][2]];
    }

    public function run(int $scale = 1, int $seed = 48, bool $fresh = false): void
    {
        if (app()->isProduction()) throw new RuntimeException('Demo data must never run in production.');
        if ($fresh) { $this->clear(); $this->clearInterruptedAccounts(); }
        if (DB::table('demo_data_runs')->exists()) { $this->line('Large demo data already exists - use --fresh to rebuild it.'); return; }
        mt_srand($seed); $started = microtime(true); $this->batch = (string) Str::uuid(); $this->call(DatabaseSeeder::class);
        $password = env('DEMO_PASSWORD') ?: Str::password(18, symbols: true);
        $now = now(); $stamp = $now->toDateTimeString(); $passwordHash = Hash::make($password);
        $this->line('1/6 Categories, suppliers and accounts');

        foreach (self::CATEGORY_TREE as $group => $children) {
            $gid = DB::table('categories')->insertGetId(['name' => $group, 'slug' => 'catalog-'.Str::slug($group), 'created_at' => $stamp, 'updated_at' => $stamp]);
            foreach ($children as $child) DB::table('categories')->insert(['parent_id' => $gid, 'name' => $child, 'slug' => 'catalog-'.Str::slug($group.'-'.$child), 'created_at' => $stamp, 'updated_at' => $stamp]);
        }
        $categories = DB::table('categories')->where('slug', 'like', 'catalog-%')->whereNotNull('parent_id')->pluck('id')->all();
        $admins = $this->users(array_map(fn ($n) => ['name' => $this->person($n), 'email' => "admin$n@demo.48source.test", 'role' => 'admin'], range(1, 3)), $passwordHash, $stamp);
        $buyerRows = array_map(fn ($n) => ['name' => $this->person($n + 10), 'email' => "bulk.buyer$n@demo.48source.test", 'role' => 'buyer', 'department' => ['Kapital layihələri', 'İT və rəqəmsal həllər', 'İstismar və logistika', 'İstehsalat'][$n % 4]], range(1, 40));
        $buyers = $this->users($buyerRows, $passwordHash, $stamp);
        $supplierRows = []; $names = ['Atlas', 'Caspian', 'Nordic', 'Orion', 'Vector', 'Meridian', 'Körpü', 'Şimal']; $cities = ['Baku', 'Ganja', 'Tbilisi', 'Riga', 'Ankara'];
        foreach (range(1, 1000 * $scale) as $n) { $status = ['active', 'pending', 'inactive', 'blocked'][intdiv($n - 1, max(1, 250 * $scale)) % 4]; $supplierRows[] = ['name' => self::PREFIX.$names[$n % count($names)]." Procurement $n", 'legal_name' => self::PREFIX."Trading $n LLC", 'category_id' => $categories[$n % count($categories)], 'city' => $cities[$n % count($cities)], 'country' => 'Inventedland', 'contact_name' => "Demo Contact $n", 'email' => "supplier$n@demo.48source.test", 'phone' => '+994 50 555 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'status' => $status, 'status_reason' => $status === 'blocked' ? 'Demo compliance hold.' : null, 'rating_tenths' => 25 + ($n % 26), 'approved_at' => $status === 'active' ? $now->copy()->subDays(30 + ($n % 400))->toDateTimeString() : null, 'approved_by' => $admins[0], 'created_at' => $stamp, 'updated_at' => $stamp]; }
        $supplierRows = [];
        foreach (range(1, 1000 * $scale) as $n) {
            $status = ['active', 'pending', 'inactive', 'blocked'][intdiv($n - 1, max(1, 250 * $scale)) % 4];
            $supplierRows[] = ['name' => $this->company($n), 'legal_name' => $this->company($n), 'category_id' => $categories[$n % count($categories)], 'city' => self::CITIES[$n % count(self::CITIES)], 'country' => 'Azərbaycan', 'contact_name' => $this->person($n + 100), 'email' => "supplier$n@demo.48source.test", 'phone' => '+994 50 555 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'status' => $status, 'status_reason' => $status === 'blocked' ? 'Uyğunluq sənədləri yenilənənədək dayandırılıb.' : null, 'rating_tenths' => 25 + ($n % 26), 'approved_at' => $status === 'active' ? $now->copy()->subDays(30 + ($n % 400))->toDateTimeString() : null, 'approved_by' => $admins[0], 'created_at' => $stamp, 'updated_at' => $stamp];
        }
        $this->insert('suppliers', $supplierRows); $supplierIds = DB::table('suppliers')->whereIn('email', array_column($supplierRows, 'email'))->orderBy('id')->pluck('id')->all();
        $accountRows = []; foreach ($supplierIds as $i => $sid) { $n = $i + 1; $accountRows[] = ['name' => "Demo Supplier User $n", 'email' => "supplier.user$n@demo.48source.test", 'role' => 'supplier', 'supplier_id' => $sid]; if ($n % 3 === 0) $accountRows[] = ['name' => "Demo Field Worker $n", 'email' => "field$n@demo.48source.test", 'role' => 'supplier_worker', 'supplier_id' => $sid]; }
        $accountRows = [];
        foreach ($supplierIds as $i => $sid) { $n = $i + 1; $accountRows[] = ['name' => $this->person($n + 300), 'email' => "supplier.user$n@demo.48source.test", 'role' => 'supplier', 'supplier_id' => $sid]; if ($n % 3 === 0) $accountRows[] = ['name' => $this->person($n + 600), 'email' => "field$n@demo.48source.test", 'role' => 'supplier_worker', 'supplier_id' => $sid]; }
        $this->users($accountRows, $passwordHash, $stamp); $supplierUsers = DB::table('users')->where('email', 'like', 'supplier.user%@demo.48source.test')->pluck('id', 'supplier_id')->all();

        $this->line('2/6 RFQs, invitations, quotations and orders');
        $terms = ['Net 15', 'Net 30', 'Net 45']; $rfqs = []; $rfqCount = 2000 * $scale;
        foreach (range(1, $rfqCount) as $n) { $status = $n <= 1125 * $scale ? 'awarded' : ['draft', 'open', 'closed', 'cancelled'][$n % 4]; $when = $now->copy()->subDays(mt_rand(1, 540)); $rfqs[] = ['number' => sprintf('RFQ-%d-D%05d', $now->year, $n), 'title' => self::PREFIX.($n === 1 ? str_repeat('Long construction procurement title ', 6) : ['Steel reinforcement', 'Industrial pump', 'Network hardware', 'Facility cleaning', 'Freight transport'][$n % 5])." request $n", 'description' => 'Invented procurement request for realistic searching and reporting.', 'type' => $n % 4 === 0 ? 'public' : 'invited', 'category_id' => $categories[$n % count($categories)], 'buyer_id' => $buyers[$n % 39], 'status' => $status, 'currency' => ['USD', 'EUR', 'AZN'][$n % 3], 'budget_cents' => $n % 97 === 0 ? 0 : (50000 + ($n % 700) * 10000), 'deadline_at' => $when->copy()->addDays(10)->toDateTimeString(), 'delivery_location' => 'Demo depot '.($n % 12 + 1), 'required_by' => $when->copy()->addDays(45)->toDateString(), 'requirements' => 'Demo specification and quality certificates.', 'payment_terms' => $terms[$n % 3], 'scoring_preset' => 'balanced', 'sealed' => $n % 7 === 0, 'round' => 1, 'published_at' => $status === 'draft' ? null : $when->toDateTimeString(), 'closed_at' => in_array($status, ['closed', 'awarded'], true) ? $when->copy()->addDays(10)->toDateTimeString() : null, 'awarded_at' => $status === 'awarded' ? $when->copy()->addDays(12)->toDateTimeString() : null, 'cancelled_at' => $status === 'cancelled' ? $when->copy()->addDays(3)->toDateTimeString() : null, 'cancel_reason' => $status === 'cancelled' ? 'Demo scope changed.' : null, 'created_at' => $when->toDateTimeString(), 'updated_at' => $when->toDateTimeString()]; }
        $rfqs = [];
        foreach (range(1, $rfqCount) as $n) {
            $status = $n <= 1125 * $scale ? 'awarded' : ['draft', 'open', 'closed', 'cancelled'][$n % 4]; $when = $now->copy()->subDays(mt_rand(1, 360)); $item = $this->catalogueItem($n);
            $rfqs[] = ['number' => sprintf('RFQ-%d-D%05d', $now->year, $n), 'title' => $item['name'].' - '.$this->project($n), 'description' => $item['name'].' tədarükü üçün texniki və kommersiya təklifi sorğusu.', 'type' => $n % 4 === 0 ? 'public' : 'invited', 'category_id' => $categories[$n % count($categories)], 'buyer_id' => $buyers[$n % 39], 'status' => $status, 'currency' => ['AZN', 'USD', 'EUR'][$n % 3], 'budget_cents' => $n % 97 === 0 ? 0 : $item['price'] * (10 + $n % 90), 'deadline_at' => $when->copy()->addDays(10)->toDateTimeString(), 'delivery_location' => $this->project($n), 'required_by' => $when->copy()->addDays(45)->toDateString(), 'requirements' => 'Keyfiyyət sertifikatları və uyğunluq sənədləri təqdim edilməlidir.', 'payment_terms' => $terms[$n % 3], 'scoring_preset' => 'balanced', 'sealed' => $n % 7 === 0, 'round' => 1, 'published_at' => $status === 'draft' ? null : $when->toDateTimeString(), 'closed_at' => in_array($status, ['closed', 'awarded'], true) ? $when->copy()->addDays(10)->toDateTimeString() : null, 'awarded_at' => $status === 'awarded' ? $when->copy()->addDays(12)->toDateTimeString() : null, 'cancelled_at' => $status === 'cancelled' ? $when->copy()->addDays(3)->toDateTimeString() : null, 'cancel_reason' => $status === 'cancelled' ? 'Layihə həcmi yenidən nəzərdən keçirilir.' : null, 'created_at' => $when->toDateTimeString(), 'updated_at' => $when->toDateTimeString()];
        }
        $this->insert('rfqs', $rfqs); $rfqIds = DB::table('rfqs')->where('number', 'like', 'RFQ-'.$now->year.'-D%')->orderBy('id')->pluck('id')->all();
        $items = []; foreach ($rfqIds as $i => $id) { $lines = $i === 0 ? 25 : ($i === 1 ? 1 : 1 + ($i % 8)); for ($p = 1; $p <= $lines; $p++) $items[] = ['rfq_id' => $id, 'position' => $p, 'name' => ['Rebar B500B', 'Industrial valve', 'Laptop workstation', 'Safety helmet', 'Freight service'][($i + $p) % 5]." $p", 'description' => 'Invented line item.', 'quantity_milli' => (10 + (($i + $p) % 200)) * 1000, 'unit' => 'pcs', 'target_price_cents' => 1000 + (($i + $p) % 9000), 'created_at' => $stamp, 'updated_at' => $stamp]; }
        $items = []; foreach ($rfqIds as $i => $id) { $item = $this->catalogueItem($i); $items[] = ['rfq_id' => $id, 'position' => 1, 'name' => $item['name'], 'description' => 'Layihə üzrə tələb olunan material və ya xidmət.', 'quantity_milli' => (10 + $i % 90) * 1000, 'unit' => $item['unit'], 'target_price_cents' => $item['price'], 'created_at' => $stamp, 'updated_at' => $stamp]; }
        $this->insert('rfq_items', $items); $firstItems = DB::table('rfq_items')->selectRaw('MIN(id) id, rfq_id')->whereIn('rfq_id', $rfqIds)->groupBy('rfq_id')->pluck('id', 'rfq_id')->all();
        $invitations = []; $quotes = []; foreach ($rfqIds as $i => $rid) { $quoteN = $i < 1125 * $scale ? 5 : ($i % 5 === 0 ? 0 : 5); for ($q = 0; $q < max(3, $quoteN); $q++) { $sid = $supplierIds[($i * 7 + $q) % (250 * $scale)]; $invitations[] = ['rfq_id' => $rid, 'supplier_id' => $sid, 'status' => $q < $quoteN ? 'accepted' : 'pending', 'decline_reason' => null, 'responded_at' => $stamp, 'created_at' => $stamp, 'updated_at' => $stamp]; if ($q < $quoteN) $quotes[] = ['rfq_id' => $rid, 'supplier_id' => $sid, 'submitted_by' => $supplierUsers[$sid] ?? null, 'status' => $i < 1125 * $scale && $q === 0 ? 'won' : ($q === 3 && $i % 9 === 0 ? 'withdrawn' : 'submitted'), 'currency' => ['USD','EUR','AZN'][$i % 3], 'delivery_days' => 3 + ($q * 2), 'payment_terms' => $terms[$q % 3], 'valid_until' => $now->copy()->addDays($i % 11 === 0 ? -1 : 30)->toDateString(), 'notes' => 'Invented quotation.', 'total_cents' => 100000 + $i * 23 + $q * 1000, 'revision' => $q === 2 && $i % 7 === 0 ? 1 : 0, 'submitted_at' => $stamp, 'created_at' => $stamp, 'updated_at' => $stamp]; } }
        $this->insert('rfq_invitations', $invitations); $this->insert('quotes', $quotes);
        $quoteRows = DB::table('quotes')->whereIn('rfq_id', $rfqIds)->get(['id','rfq_id','total_cents']); $quoteItems = []; foreach ($quoteRows as $q) $quoteItems[] = ['quote_id' => $q->id, 'rfq_item_id' => $firstItems[$q->rfq_id], 'unit_price_cents' => $q->total_cents, 'total_cents' => $q->total_cents, 'created_at' => $stamp, 'updated_at' => $stamp]; $this->insert('quote_items', $quoteItems);
        $this->makeOrders($rfqIds, $supplierIds, $buyers, $firstItems, $supplierUsers, $stamp, $now, $scale);

        $this->line('3/6 Requisitions, contracts, supplier records and conversations');
        $this->supportingRecords($categories, $buyers, $supplierIds, $supplierUsers, $rfqIds, $stamp, $now, $scale);
        $this->engagementRecords($buyers, $supplierIds, $supplierUsers, $rfqIds, $stamp, $now, $scale);
        $this->line('4/6 Markets, products, offers and marketplace orders');
        $this->marketplace($categories, $buyers, $passwordHash, $stamp, $now, $scale);
        $this->polishLegacyDisplayData($now);
        $this->line('5/6 Reports, notifications and sequences');
        foreach ($categories as $category) foreach ([$now->year - 1, $now->year] as $year) DB::table('budgets')->updateOrInsert(['year' => $year, 'category_id' => $category], ['amount_cents' => $category % 13 === 0 ? 0 : 10000000 + $category * 1000, 'created_at' => $stamp, 'updated_at' => $stamp]);
        DB::table('activity_logs')->insert(['user_id' => $buyers[0], 'actor_name' => $this->person(10), 'area' => 'RFQs', 'action' => 'Test məlumatları yaradıldı', 'subject_type' => null, 'subject_id' => null, 'subject_label' => 'Satınalma məlumat dəsti', 'supplier_id' => null, 'meta' => json_encode(['seed' => $seed]), 'created_at' => $stamp]);
        $this->syncSequences($rfqCount, 900 * $scale); DB::table('demo_data_runs')->insert(['seed' => $seed, 'scale' => $scale, 'password_hint' => substr($password, 0, 2).'...', 'created_at' => $stamp, 'updated_at' => $stamp]);
        $this->line('6/6 Done in '.number_format(microtime(true) - $started, 1).' seconds.');
        $this->table(['Table', 'Rows'], collect(['suppliers','users','rfqs','rfq_items','quotes','purchase_orders','products','offers','market_orders'])->map(fn ($t) => [$t, DB::table($t)->count()])->all());
        $this->info('All demo accounts use this password: '.$password); $this->table(['Role', 'Email'], [['Administrator', 'admin1@demo.48source.test'], ['Buyer', 'bulk.buyer1@demo.48source.test'], ['Supplier', 'supplier.user1@demo.48source.test']]);
    }

    private function users(array $rows, string $hash, string $stamp): array { foreach ($rows as &$r) $r += ['password' => $hash, 'status' => 'active', 'email_verified_at' => $stamp, 'theme' => 'light', 'locale' => 'en', 'two_factor' => false, 'email_notices' => true, 'created_at' => $stamp, 'updated_at' => $stamp]; unset($r); $this->insert('users', $rows); return DB::table('users')->whereIn('email', array_column($rows, 'email'))->orderBy('id')->pluck('id')->all(); }
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $before = DB::table($table)->max('id') ?? 0;
            DB::table($table)->insert($chunk);
            $ids = DB::table($table)->where('id', '>', $before)->orderBy('id')->limit(count($chunk))->pluck('id');
            $this->track($table, $ids->all());
        }
    }
    private function track(string $table, array $ids): void
    {
        if ($this->batch === '' || $ids === []) return;
        DB::table('demo_records')->insert(array_map(fn ($id) => ['table_name' => $table, 'record_id' => $id, 'batch' => $this->batch, 'created_at' => now()], $ids));
    }
    private function line(string $text): void { $this->command?->info($text); }
    private function table(array $head, array $rows): void { $this->command?->table($head, $rows); }
    private function info(string $text): void { $this->command?->info($text); }
    private function syncSequences(int $rfqs, int $orders): void { foreach (['RFQ' => $rfqs, 'PO' => $orders, 'REQ' => 400, 'CON' => 120, 'INV' => 1200, 'RFI' => 150, 'MO' => 3000] as $name => $value) DB::table('sequences')->updateOrInsert(['name' => $name, 'year' => now()->year], ['value' => $value]); }

    /** Replace old volume-fixture labels after their relationships have been created. */
    private function polishLegacyDisplayData($now): void
    {
        $set = fn (string $table, array $values) => DB::table('demo_records')->where('batch', $this->batch)->where('table_name', $table)->orderBy('record_id')->chunkById(500, fn ($rows) => DB::table($table)->whereIn('id', $rows->pluck('record_id'))->update($values), 'record_id');
        $set('rfq_items', ['description' => 'Layihə üzrə tələb olunan material və ya xidmət.']);
        $set('purchase_order_items', ['name' => 'Təsdiqlənmiş tədarük xətti']);
        $set('requisitions', ['justification' => 'Layihə planına uyğun təsdiqlənmiş ehtiyac.']);
        $set('requisition_items', ['name' => 'Layihə materialı']);
        $set('contract_items', ['name' => 'Çərçivə müqaviləsi üzrə material']);
        $set('contract_releases', ['note' => 'Təsdiqlənmiş çağırış sifarişi']);
        $set('goods_receipts', ['note' => 'Anbar qəbul qeydi']);
        $set('supplier_documents', ['title' => 'Uyğunluq sənədi']);
        $set('supplier_actions', ['title' => 'Uyğunluq tədbiri', 'detail' => 'Təchizatçı ilə razılaşdırılmış düzəldici tədbir.']);
        $set('field_assessments', ['title' => 'Sahəyə giriş və boşaltma qiymətləndirməsi', 'findings' => 'Sahə yoxlamasının nəticələri qeydə alınıb.']);
        $set('rfis', ['description' => 'Təchizatçı imkanları və çatdırılma müddəti barədə məlumat sorğusu.']);
        $set('messages', ['body' => 'Tədarük üzrə yenilənmiş məlumat göndərildi.']);
        $set('market_orders', ['deliver_to' => 'Layihə sahəsinin qəbul məntəqəsi', 'note' => 'Bazar sifarişi', 'cancel_reason' => 'Sifarişin həcmi dəyişdirildi.']);
        $set('products', ['description' => 'Sənaye və tikinti üçün kataloq məhsulu.']);
        $set('activity_logs', ['actor_name' => $this->person(1), 'action' => 'Test məlumatları yaradıldı', 'subject_label' => 'Satınalma məlumat dəsti']);

        foreach (DB::table('demo_records')->where('batch', $this->batch)->where('table_name', 'users')->orderBy('record_id')->pluck('record_id')->values() as $i => $id) DB::table('users')->where('id', $id)->update(['name' => $this->person($i + 500)]);
        foreach (DB::table('demo_records')->where('batch', $this->batch)->where('table_name', 'requisitions')->orderBy('record_id')->pluck('record_id')->values() as $i => $id) DB::table('requisitions')->where('id', $id)->update(['title' => $this->catalogueItem($i)['name'].' tələbi - '.$this->project($i)]);
        foreach (DB::table('demo_records')->where('batch', $this->batch)->where('table_name', 'contracts')->orderBy('record_id')->pluck('record_id')->values() as $i => $id) DB::table('contracts')->where('id', $id)->update(['title' => $this->catalogueItem($i)['name'].' üzrə çərçivə müqaviləsi']);
        foreach (DB::table('demo_records')->where('batch', $this->batch)->where('table_name', 'rfis')->orderBy('record_id')->pluck('record_id')->values() as $i => $id) DB::table('rfis')->where('id', $id)->update(['title' => $this->catalogueItem($i)['name'].' üzrə bazar araşdırması']);
        foreach (DB::table('demo_records')->where('batch', $this->batch)->where('table_name', 'conversations')->orderBy('record_id')->pluck('record_id')->values() as $i => $id) DB::table('conversations')->where('id', $id)->update(['subject' => $this->project($i).' üzrə tədarük müzakirəsi']);
        foreach (DB::table('demo_records')->where('batch', $this->batch)->where('table_name', 'markets')->orderBy('record_id')->pluck('record_id')->values() as $i => $id) DB::table('markets')->where('id', $id)->update(['name' => $this->marketName($i), 'city' => self::CITIES[$i % count(self::CITIES)], 'address' => 'Sənaye məhəlləsi']);
        foreach (DB::table('demo_records')->where('batch', $this->batch)->where('table_name', 'stores')->orderBy('record_id')->pluck('record_id')->values() as $i => $id) DB::table('stores')->where('id', $id)->update(['name' => $this->company($i + 2000).' satış məntəqəsi']);
        foreach (DB::table('demo_records')->where('batch', $this->batch)->where('table_name', 'products')->orderBy('record_id')->pluck('record_id')->values() as $i => $id) DB::table('products')->where('id', $id)->update(['name' => $this->catalogueItem($i)['name'], 'unit' => $this->catalogueItem($i)['unit']]);
    }
    private function clear(): void
    {
        // The small DemoSeeder uses the same safe domain, so never delete by email
        // alone. Every row made here has the explicit "Demo " label or DEMO- SKU.
        DB::transaction(function () {
            $this->clearTracked();
            DB::table('demo_data_runs')->delete();
            $poIds = DB::table('purchase_orders')->where('number', 'like', 'PO-%-D%')->pluck('id')->all();
            $invoiceIds = DB::table('invoices')->whereIn('purchase_order_id', $poIds)->pluck('id')->all();
            $receiptIds = DB::table('goods_receipts')->whereIn('purchase_order_id', $poIds)->pluck('id')->all();
            DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
            DB::table('invoices')->whereIn('purchase_order_id', $poIds)->delete();
            $demoInvoiceIds = DB::table('invoices')->whereIn('submitted_by', DB::table('users')->where('name', 'like', self::PREFIX.'%')->select('id'))->pluck('id')->all();
            DB::table('invoice_items')->whereIn('invoice_id', $demoInvoiceIds)->delete();
            DB::table('invoices')->whereIn('id', $demoInvoiceIds)->delete();
            DB::table('goods_receipt_items')->whereIn('goods_receipt_id', $receiptIds)->delete();
            DB::table('goods_receipts')->whereIn('purchase_order_id', $poIds)->delete();
            DB::table('purchase_order_items')->whereIn('purchase_order_id', $poIds)->delete();
            $releaseIds = DB::table('contract_releases')->where('number', 'like', 'REL-%-D%')->pluck('id')->all();
            DB::table('contract_release_items')->whereIn('contract_release_id', $releaseIds)->delete();
            DB::table('contract_releases')->whereIn('contract_id', DB::table('contracts')->where('title', 'like', self::PREFIX.' framework%')->select('id'))->delete();
            DB::table('contract_items')->whereIn('contract_id', DB::table('contracts')->where('title', 'like', self::PREFIX.' framework%')->select('id'))->delete();
            DB::table('contracts')->where('title', 'like', self::PREFIX.' framework%')->delete();
            $marketOrderIds = DB::table('market_orders')->where('number', 'like', 'MO-%-D%')->pluck('id')->all();
            DB::table('worker_tasks')->whereIn('market_order_id', $marketOrderIds)->delete();
            DB::table('market_order_items')->whereIn('market_order_id', $marketOrderIds)->delete();
            DB::table('market_orders')->where('number', 'like', 'MO-%-D%')->delete();
            $rfqIds = DB::table('rfqs')->where('title', 'like', self::PREFIX.'%')->pluck('id')->all();
            DB::table('purchase_orders')->whereIn('rfq_id', $rfqIds)->delete();
            DB::table('field_assessments')->where('number', 'like', 'FA-%-D%')->delete();
            DB::table('rfq_questions')->whereIn('rfq_id', $rfqIds)->delete();
            DB::table('auction_bids')->whereIn('auction_id', DB::table('auctions')->whereIn('rfq_id', $rfqIds)->select('id'))->delete();
            DB::table('auctions')->whereIn('rfq_id', $rfqIds)->delete();
            DB::table('bafo_rounds')->whereIn('rfq_id', $rfqIds)->delete();
            DB::table('rfqs')->where('title', 'like', self::PREFIX.'%')->delete();
            DB::table('requisition_approvals')->whereIn('requisition_id', DB::table('requisitions')->where('title', 'like', self::PREFIX.' requisition%')->select('id'))->delete();
            DB::table('requisition_items')->whereIn('requisition_id', DB::table('requisitions')->where('title', 'like', self::PREFIX.' requisition%')->select('id'))->delete();
            DB::table('requisitions')->where('title', 'like', self::PREFIX.'%')->delete();
            DB::table('rfi_responses')->whereIn('rfi_id', DB::table('rfis')->where('title', 'like', self::PREFIX.' market sounding%')->select('id'))->delete();
            DB::table('rfis')->where('title', 'like', self::PREFIX.' market sounding%')->delete();
            $conversationIds = DB::table('conversations')->where('subject', 'like', 'Demo procurement conversation%')->select('id');
            DB::table('conversation_reads')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('messages')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('conversations')->where('subject', 'like', 'Demo procurement conversation%')->delete();
            $demoUsers = DB::table('users')->where('name', 'like', self::PREFIX.'%')->select('id');
            DB::table('supplier_documents')->whereIn('uploaded_by', $demoUsers)->delete();
            DB::table('supplier_scorecards')->whereIn('created_by', DB::table('users')->where('name', 'like', self::PREFIX.'%')->select('id'))->delete();
            DB::table('supplier_actions')->whereIn('owner_id', DB::table('users')->where('name', 'like', self::PREFIX.'%')->select('id'))->delete();
            DB::table('offers')->whereIn('product_id', DB::table('products')->where('sku', 'like', 'DEMO-%')->select('id'))->delete();
            DB::table('products')->where('sku', 'like', 'DEMO-%')->delete();
            DB::table('stores')->where('name', 'like', self::PREFIX.'%')->delete();
            DB::table('markets')->where('name', 'like', self::PREFIX.'%')->delete();
            DB::table('suppliers')->where('name', 'like', self::PREFIX.'%')->delete();
            DB::table('users')->where('name', 'like', self::PREFIX.'%')->delete();
            DB::table('categories')->where('slug', 'like', 'demo-%')->delete();
        });
    }

    /** A failed pre-tracker run can only have created these deterministic account addresses. */
    private function clearInterruptedAccounts(): void
    {
        // A run interrupted before the marker table is available still has these
        // deterministic addresses. Remove their dependent supplier rows first so
        // a following --fresh run cannot accidentally select two generations.
        $supplierIds = DB::table('suppliers')->where('email', 'like', 'supplier%@demo.48source.test')->pluck('id');
        if ($supplierIds->isNotEmpty()) {
            DB::table('supplier_documents')->whereIn('supplier_id', $supplierIds)->delete();
            DB::table('supplier_scorecards')->whereIn('supplier_id', $supplierIds)->delete();
            DB::table('supplier_actions')->whereIn('supplier_id', $supplierIds)->delete();
            DB::table('field_assessments')->whereIn('supplier_id', $supplierIds)->delete();
            DB::table('rfi_responses')->whereIn('supplier_id', $supplierIds)->delete();
            DB::table('rfq_questions')->whereIn('supplier_id', $supplierIds)->delete();
            DB::table('quotes')->whereIn('supplier_id', $supplierIds)->delete();
            DB::table('rfq_invitations')->whereIn('supplier_id', $supplierIds)->delete();
            DB::table('suppliers')->whereIn('id', $supplierIds)->delete();
        }
        $catalogIds = DB::table('categories')->where('slug', 'like', 'catalog-%')->pluck('id');
        DB::table('categories')->whereIn('parent_id', $catalogIds)->delete();
        DB::table('categories')->whereIn('id', $catalogIds)->delete();
        DB::table('users')->where(fn ($q) => $q->where('email', 'like', 'admin%@demo.48source.test')->orWhere('email', 'like', 'bulk.buyer%@demo.48source.test')->orWhere('email', 'like', 'supplier.user%@demo.48source.test')->orWhere('email', 'like', 'field%@demo.48source.test')->orWhere('email', 'like', 'seller.%@demo.48source.test')->orWhere('email', 'like', 'market.worker.%@demo.48source.test'))->delete();
    }
    /** Remove marker-owned rows before using the legacy name-based fallback. */
    private function clearTracked(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('demo_records')) return;
        $tables = ['invoice_items', 'invoices', 'goods_receipt_items', 'goods_receipts', 'purchase_order_items', 'purchase_orders', 'quote_items', 'quotes', 'rfq_invitations', 'rfq_questions', 'auction_bids', 'auctions', 'bafo_rounds', 'rfq_items', 'rfqs', 'contract_release_items', 'contract_releases', 'contract_items', 'contracts', 'requisition_approvals', 'requisition_items', 'requisitions', 'rfi_responses', 'rfis', 'conversation_reads', 'messages', 'conversations', 'worker_tasks', 'market_order_items', 'market_orders', 'offers', 'products', 'stores', 'markets', 'supplier_documents', 'supplier_scorecards', 'supplier_actions', 'field_assessments', 'suppliers', 'users', 'categories'];
        foreach ($tables as $table) {
            $ids = DB::table('demo_records')->where('table_name', $table)->pluck('record_id');
            if ($ids->isNotEmpty()) DB::table($table)->whereIn('id', $ids)->delete();
        }
        DB::table('demo_records')->delete();
    }
    private function makeOrders(array $rfqs, array $suppliers, array $buyers, array $firstItems, array $supplierUsers, string $stamp, $now, int $scale): void
    {
        $orders = []; $items = [];
        $quotes = DB::table('quotes')->whereIn('rfq_id', $rfqs)->orderBy('rfq_id')->orderBy('id')->get()->unique('rfq_id')->take(900 * $scale)->values();
        foreach ($quotes as $i => $quote) {
            $status = ['draft','issued','acknowledged','in_transit','delivered','invoiced','cancelled'][$i % 7];
            $orders[] = ['number' => sprintf('PO-%d-D%05d', $now->year, $i + 1), 'rfq_id' => $quote->rfq_id, 'supplier_id' => $quote->supplier_id, 'quote_id' => $quote->id, 'buyer_id' => $buyers[$i % 39], 'status' => $status, 'currency' => $quote->currency, 'total_cents' => $quote->total_cents, 'payment_terms' => 'Net 30', 'delivery_location' => 'Demo depot', 'expected_at' => $now->copy()->subDays($i % 180)->toDateString(), 'award_justification' => 'Lowest compliant demo quotation.', 'issued_at' => $stamp, 'acknowledged_at' => in_array($status, ['acknowledged','in_transit','delivered','invoiced'], true) ? $stamp : null, 'shipped_at' => in_array($status, ['in_transit','delivered','invoiced'], true) ? $stamp : null, 'delivered_at' => in_array($status, ['delivered','invoiced'], true) ? $stamp : null, 'invoiced_at' => $status === 'invoiced' ? $stamp : null, 'cancelled_at' => $status === 'cancelled' ? $stamp : null, 'created_at' => $stamp, 'updated_at' => $stamp];
        }
        $this->insert('purchase_orders', $orders);
        $poIds = DB::table('purchase_orders')->where('number', 'like', 'PO-'.$now->year.'-D%')->orderBy('id')->pluck('id')->all();
        foreach ($quotes as $i => $quote) $items[] = ['purchase_order_id' => $poIds[$i], 'rfq_item_id' => $firstItems[$quote->rfq_id], 'name' => 'Demo awarded line '.($i + 1), 'quantity_milli' => 1000, 'unit' => 'pcs', 'unit_price_cents' => $quote->total_cents, 'total_cents' => $quote->total_cents, 'created_at' => $stamp, 'updated_at' => $stamp];
        $this->insert('purchase_order_items', $items);
    }

    private function supportingRecords(array $categories, array $buyers, array $suppliers, array $supplierUsers, array $rfqs, string $stamp, $now, int $scale): void
    {
        $reqs = []; $reqItems = []; $approvals = [];
        foreach (range(1, 400 * $scale) as $n) {
            $status = ['draft','pending','approved','rejected','cancelled'][$n % 5]; $total = 100000 + $n * 100;
            $reqs[] = ['number' => sprintf('REQ-%d-D%04d', $now->year, $n), 'requester_id' => $buyers[$n % 39], 'category_id' => $categories[$n % count($categories)], 'rfq_id' => null, 'title' => self::PREFIX." requisition $n", 'justification' => 'Invented business need.', 'needed_by' => $now->copy()->addDays($n % 90)->toDateString(), 'currency' => ['USD','EUR','AZN'][$n % 3], 'total_cents' => $total, 'status' => $status, 'submitted_at' => $status === 'draft' ? null : $stamp, 'decided_at' => in_array($status, ['approved','rejected'], true) ? $stamp : null, 'created_at' => $stamp, 'updated_at' => $stamp];
        }
        $this->insert('requisitions', $reqs); $reqIds = DB::table('requisitions')->where('title', 'like', self::PREFIX.' requisition%')->pluck('id')->all();
        foreach ($reqIds as $i => $id) { $total = 100000 + ($i + 1) * 100; $reqItems[] = ['requisition_id' => $id, 'name' => 'Demo requisition item '.($i + 1), 'quantity_milli' => 1000, 'unit' => 'pcs', 'est_price_cents' => $total, 'total_cents' => $total, 'created_at' => $stamp, 'updated_at' => $stamp]; foreach ([1,2] as $step) $approvals[] = ['requisition_id' => $id, 'step' => $step, 'role' => $step === 1 ? 'buyer' : 'admin', 'label' => $step === 1 ? 'Budget owner' : 'Procurement approval', 'status' => $i % 5 === 3 ? 'rejected' : ($i % 5 === 2 ? 'approved' : ($step === 1 ? 'pending' : 'waiting')), 'approver_id' => $i % 5 > 1 ? $buyers[$i % 39] : null, 'comment' => null, 'decided_at' => $i % 5 > 1 ? $stamp : null, 'created_at' => $stamp, 'updated_at' => $stamp]; }
        $this->insert('requisition_items', $reqItems); $this->insert('requisition_approvals', $approvals);

        $contracts=[]; foreach (range(1, 120 * $scale) as $n) { $status=['draft','active','active','expired','terminated'][$n%5]; $ceiling=10000000 + $n*10000; $contracts[]=['number'=>sprintf('CON-%d-D%04d',$now->year,$n),'supplier_id'=>$suppliers[$n%count($suppliers)],'owner_id'=>$buyers[$n%39],'rfq_id'=>null,'title'=>self::PREFIX." framework contract $n",'status'=>$status,'currency'=>['USD','EUR','AZN'][$n%3],'ceiling_cents'=>$ceiling,'released_cents'=>$status==='active' ? intdiv($ceiling, $n%4===0 ? 1 : 2) : 0,'starts_on'=>$now->copy()->subMonths(4)->toDateString(),'ends_on'=>$status==='expired' ? $now->copy()->subDay()->toDateString() : $now->copy()->addDays($n%4===0 ? 20 : 180)->toDateString(),'payment_terms'=>'Net 30','terms'=>'Invented framework terms.','end_reason'=>$status==='terminated'?'Demo scope ended.':null,'created_at'=>$stamp,'updated_at'=>$stamp]; }
        $this->insert('contracts',$contracts); $contractIds=DB::table('contracts')->where('title','like',self::PREFIX.' framework%')->pluck('id')->all(); $contractItems=[]; $releases=[]; $releaseItems=[];
        foreach ($contractIds as $i=>$id) { $price=100000+$i; $contractItems[]=['contract_id'=>$id,'name'=>'Demo contract item '.($i+1),'unit'=>'pcs','unit_price_cents'=>$price,'created_at'=>$stamp,'updated_at'=>$stamp]; }
        $this->insert('contract_items',$contractItems); $itemIds=DB::table('contract_items')->whereIn('contract_id',$contractIds)->pluck('id','contract_id')->all(); foreach ($contractIds as $i=>$id) { if($i%3) { $total=(100000+$i)*($i%4===0?100:50); $releases[]=['number'=>sprintf('REL-%d-D%04d',$now->year,$i+1),'contract_id'=>$id,'created_by'=>$buyers[$i%39],'status'=>['issued','accepted','delivered'][$i%3],'total_cents'=>$total,'needed_by'=>$now->copy()->addDays(10)->toDateString(),'note'=>'Demo release','accepted_at'=>$i%3>0?$stamp:null,'delivered_at'=>$i%3===2?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp]; } }
        $this->insert('contract_releases',$releases); foreach (DB::table('contract_releases')->where('number','like','REL-'.$now->year.'-D%')->get() as $r) { $price=DB::table('contract_items')->where('contract_id',$r->contract_id)->value('unit_price_cents'); $releaseItems[]=['contract_release_id'=>$r->id,'contract_item_id'=>$itemIds[$r->contract_id],'name'=>'Demo contract item','unit'=>'pcs','quantity_milli'=>intdiv($r->total_cents,$price)*1000,'unit_price_cents'=>$price,'total_cents'=>$r->total_cents,'created_at'=>$stamp,'updated_at'=>$stamp]; } $this->insert('contract_release_items',$releaseItems);

        $poItems=DB::table('purchase_order_items')->join('purchase_orders','purchase_orders.id','=','purchase_order_items.purchase_order_id')->where('purchase_orders.number', 'like', 'PO-'.$now->year.'-D%')->select('purchase_order_items.*','purchase_orders.supplier_id','purchase_orders.currency','purchase_orders.buyer_id')->get()->values(); $receipts=[];$receiptItems=[];$invoices=[];$invoiceItems=[];
        foreach($poItems as $i=>$line) { if($i%7>=3) { $accepted=$i%4===0?700:1000; $receipts[]=['number'=>sprintf('GR-%d-D%05d',$now->year,$i+1),'purchase_order_id'=>$line->purchase_order_id,'received_by'=>$line->buyer_id,'received_on'=>$now->copy()->subDays($i%120)->toDateString(),'note'=>$accepted<1000?'Partial demo receipt':'Demo receipt','created_at'=>$stamp,'updated_at'=>$stamp]; } }
        $this->insert('goods_receipts',$receipts); foreach(DB::table('goods_receipts')->where('number','like','GR-'.$now->year.'-D%')->get() as $r){$line=$poItems->firstWhere('purchase_order_id',$r->purchase_order_id);$receiptItems[]=['goods_receipt_id'=>$r->id,'purchase_order_item_id'=>$line->id,'accepted_milli'=>str_contains($r->note,'Partial')?700:1000,'rejected_milli'=>$r->id%9===0?100:0,'note'=>null,'created_at'=>$stamp,'updated_at'=>$stamp];} $this->insert('goods_receipt_items',$receiptItems);
        for($n=0;$n<1200*$scale;$n++){ $line=$poItems[$n%$poItems->count()]; $matched=$n%5===0; $qty=$matched?500:($n%3===0?1000:700); $price=$matched?$line->unit_price_cents:$line->unit_price_cents+100; $total=intdiv($qty*$price,1000); $status=['matched','disputed','approved','paid','rejected'][$n%5]; $invoices[]=['number'=>sprintf('INV-%d-D%05d',$now->year,$n+1),'supplier_ref'=>'DEMO-'.$n,'purchase_order_id'=>$line->purchase_order_id,'supplier_id'=>$line->supplier_id,'submitted_by'=>$supplierUsers[$line->supplier_id],'status'=>$status,'currency'=>$line->currency,'total_cents'=>$total,'issued_on'=>$now->copy()->subDays($n%400)->toDateString(),'due_on'=>$now->copy()->subDays($n%50)->toDateString(),'issues'=>$status==='disputed'?json_encode(['Quantity or price differs from the received goods.']):null,'decision_note'=>$status==='approved'?'Approved demo exception.':null,'decided_by'=>in_array($status,['approved','paid','rejected'],true)?$buyers[$n%39]:null,'decided_at'=>in_array($status,['approved','paid','rejected'],true)?$stamp:null,'paid_at'=>$status==='paid'?$stamp:null,'exported_at'=>$status==='paid'&&$n%2===0?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp]; }
        $this->insert('invoices',$invoices); foreach(DB::table('invoices')->where('number','like','INV-'.$now->year.'-D%')->get() as $inv){$line=$poItems->firstWhere('purchase_order_id',$inv->purchase_order_id);$invoiceItems[]=['invoice_id'=>$inv->id,'purchase_order_item_id'=>$line->id,'quantity_milli'=>intdiv($inv->total_cents*1000,$line->unit_price_cents),'unit_price_cents'=>$line->unit_price_cents,'total_cents'=>$inv->total_cents,'created_at'=>$stamp,'updated_at'=>$stamp];} $this->insert('invoice_items',$invoiceItems);
    }
    private function engagementRecords(array $buyers, array $suppliers, array $supplierUsers, array $rfqs, string $stamp, $now, int $scale): void
    {
        $documents=[];$scores=[];$actions=[];$assessments=[];
        foreach ($suppliers as $i=>$supplier) { $user=$supplierUsers[$supplier]; foreach(range(1,4) as $n)$documents[]=['supplier_id'=>$supplier,'type'=>['registration','iso','insurance','tax'][$n-1],'title'=>'Demo compliance document '.$n,'path'=>null,'original_name'=>null,'expires_on'=>$n===1?null:$now->copy()->addDays((($i+$n)%4===0)?-10:(($i+$n)%4===1?15:180))->toDateString(),'status'=>$n%3===0?'pending':'verified','uploaded_by'=>$user,'verified_by'=>$n%3===0?null:$buyers[$i%39],'created_at'=>$stamp,'updated_at'=>$stamp];
            for($q=0;$q<6;$q++){$period=$now->copy()->startOfQuarter()->subQuarters($q);$scores[]=['supplier_id'=>$supplier,'period'=>$period->year.'-Q'.$period->quarter,'quality'=>3+$i%3,'delivery'=>3+($i+$q)%3,'price'=>3+($i+1)%3,'service'=>3+($i+2)%3,'score_tenths'=>35+($i%16),'comment'=>'Invented quarterly supplier scorecard.','created_by'=>$buyers[$i%39],'created_at'=>$stamp,'updated_at'=>$stamp];}
            if($i%3===0)$actions[]=['supplier_id'=>$supplier,'title'=>'Demo corrective action '.($i+1),'detail'=>'Invented follow-up action.','due_on'=>$now->copy()->addDays($i%4===0?-8:20)->toDateString(),'status'=>$i%5===0?'done':'open','owner_id'=>$buyers[$i%39],'done_at'=>$i%5===0?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp];
        }
        $this->insert('supplier_documents',$documents);$this->insert('supplier_scorecards',$scores);$this->insert('supplier_actions',$actions);
        foreach(range(1,120*$scale) as $n){$status=['assigned','in_progress','submitted','approved','rejected'][$n%5];$assessments[]=['number'=>sprintf('FA-%d-D%04d',$now->year,$n),'supplier_id'=>$suppliers[$n%count($suppliers)],'rfq_id'=>$rfqs[$n%count($rfqs)],'requested_by'=>$buyers[$n%39],'assigned_to'=>$supplierUsers[$suppliers[$n%count($suppliers)]],'title'=>'Demo field assessment '.$n,'checklist'=>json_encode(\App\Models\FieldAssessment::DEFAULT_CHECKLIST),'answers'=>$status==='assigned'?null:json_encode([]),'findings'=>$status==='assigned'?null:'Invented findings.','status'=>$status,'due_on'=>$now->copy()->addDays($n%5===0?-4:14)->toDateString(),'submitted_at'=>in_array($status,['submitted','approved','rejected'],true)?$stamp:null,'decided_by'=>in_array($status,['approved','rejected'],true)?$buyers[$n%39]:null,'decision_note'=>$status==='rejected'?'Please address the demo finding.':null,'decided_at'=>in_array($status,['approved','rejected'],true)?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp];}
        $this->insert('field_assessments',$assessments);
        $rfis=[];$responses=[];foreach(range(1,150*$scale) as $n){$rfis[]=['number'=>sprintf('RFI-%d-D%04d',$now->year,$n),'buyer_id'=>$buyers[$n%39],'rfq_id'=>null,'title'=>self::PREFIX.' market sounding '.$n,'description'=>'Invented information request.','questions'=>json_encode(['What lead time can you offer?','What certifications do you hold?']),'status'=>'closed','deadline_at'=>$now->copy()->subDays($n%300)->toDateTimeString(),'created_at'=>$stamp,'updated_at'=>$stamp];}$this->insert('rfis',$rfis);$rfiIds=DB::table('rfis')->where('title','like',self::PREFIX.' market sounding%')->pluck('id')->all();foreach($rfiIds as $i=>$id)foreach(range(0,2) as $x){$sid=$suppliers[($i+$x)%count($suppliers)];$responses[]=['rfi_id'=>$id,'supplier_id'=>$sid,'status'=>$x===2?'declined':'answered','answers'=>$x===2?null:json_encode(['Four weeks.','ISO 9001.']),'answered_by'=>$x===2?null:$supplierUsers[$sid],'answered_at'=>$x===2?null:$stamp,'created_at'=>$stamp,'updated_at'=>$stamp];}$this->insert('rfi_responses',$responses);
        $questions=[];foreach(range(1,500*$scale) as $n){$sid=$suppliers[$n%count($suppliers)];$questions[]=['rfq_id'=>$rfqs[$n%count($rfqs)],'supplier_id'=>$sid,'asked_by'=>$supplierUsers[$sid],'question'=>'Invented supplier question '.$n,'answer'=>$n%3?'Invented buyer answer.':null,'answered_by'=>$n%3?$buyers[$n%39]:null,'answered_at'=>$n%3?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp];}$this->insert('rfq_questions',$questions);
        $bafo=[];foreach(range(1,60*$scale) as $n)$bafo[]=['rfq_id'=>$rfqs[$n],'round'=>2,'opened_by'=>$buyers[$n%39],'status'=>'closed','deadline_at'=>$now->copy()->subDays($n)->toDateTimeString(),'message'=>'Invented best and final request.','closed_at'=>$stamp,'created_at'=>$stamp,'updated_at'=>$stamp];$this->insert('bafo_rounds',$bafo);
        $auctions=[];foreach(range(1,32*$scale) as $n)$auctions[]=['rfq_id'=>$rfqs[100+$n],'opened_by'=>$buyers[$n%39],'status'=>$n<=2?'live':'ended','starts_at'=>$now->copy()->subHours(2)->toDateTimeString(),'ends_at'=>$n<=2?$now->copy()->addHours(2)->toDateTimeString():$now->copy()->subHour()->toDateTimeString(),'min_step_bp'=>50,'extend_minutes'=>2,'applied_at'=>$n<=2?null:$stamp,'created_at'=>$stamp,'updated_at'=>$stamp];$this->insert('auctions',$auctions);$bids=[];foreach(DB::table('auctions')->get() as $a)foreach(range(1,12) as $n){$sid=$suppliers[($a->id+$n)%count($suppliers)];$bids[]=['auction_id'=>$a->id,'supplier_id'=>$sid,'user_id'=>$supplierUsers[$sid],'total_cents'=>1000000-$n*1000,'created_at'=>$stamp,'updated_at'=>$stamp];}$this->insert('auction_bids',$bids);
        $conversations=[];foreach(range(1,600*$scale) as $n)$conversations[]=['supplier_id'=>$suppliers[$n%count($suppliers)],'created_by'=>$buyers[$n%39],'subject'=>'Demo procurement conversation '.$n,'subject_ref_type'=>null,'subject_ref_id'=>null,'last_message_at'=>$now->copy()->subDays($n%180)->toDateTimeString(),'created_at'=>$stamp,'updated_at'=>$stamp];$this->insert('conversations',$conversations);$messages=[];$reads=[];foreach(DB::table('conversations')->where('subject','like','Demo procurement conversation%')->get() as $i=>$c){$last=0;for($n=1;$n<=2+($i%14);$n++){$messages[]=['conversation_id'=>$c->id,'user_id'=>$n%2?$c->created_by:$supplierUsers[$c->supplier_id],'body'=>'Invented message '.$n.' for procurement discussion.','created_at'=>$stamp,'updated_at'=>$stamp];}$reads[]=['conversation_id'=>$c->id,'user_id'=>$c->created_by,'message_id'=>$last,'read_at'=>$stamp];}$this->insert('messages',$messages);$this->insert('conversation_reads',$reads);
    }

    private function marketplace(array $categories, array $buyers, string $hash, string $stamp, $now, int $scale): void
    {
        $markets=[]; foreach(range(1,12*$scale) as $n)$markets[]=['name'=>self::PREFIX." Market $n",'city'=>'Demo City '.$n,'address'=>'Demo avenue '.$n,'status'=>'active','created_at'=>$stamp,'updated_at'=>$stamp]; $this->insert('markets',$markets);
        $marketIds=DB::table('markets')->where('name','like',self::PREFIX.'%')->pluck('id')->all(); $stores=[]; foreach(range(1,90*$scale) as $n)$stores[]=['market_id'=>$marketIds[$n%count($marketIds)],'name'=>self::PREFIX." Store $n",'stall'=>'D-'.$n,'phone'=>null,'status'=>$n%13===0?'paused':'active','created_at'=>$stamp,'updated_at'=>$stamp]; $this->insert('stores',$stores);
        $storeIds=DB::table('stores')->where('name','like',self::PREFIX.'%')->pluck('id')->all(); $staff=[]; foreach($storeIds as $i=>$store){$staff[]=['name'=>'Demo Seller '.($i+1),'email'=>'seller.'.($i+1).'@demo.48source.test','role'=>'seller','store_id'=>$store]; if($i%3===0)$staff[]=['name'=>'Demo Market Worker '.($i+1),'email'=>'market.worker.'.($i+1).'@demo.48source.test','role'=>'market_worker','store_id'=>$store];} $this->users($staff,$hash,$stamp);
        $products=[]; foreach(range(1,2500*$scale) as $n)$products[]=['sku'=>'DEMO-'.str_pad((string)$n,5,'0',STR_PAD_LEFT),'name'=>['Steel plate','Network switch','Safety glove','Pump seal','Freight pallet'][$n%5]." $n",'category_id'=>$categories[$n%count($categories)],'unit'=>'pcs','description'=>'Invented catalogue product.','status'=>'active','created_at'=>$stamp,'updated_at'=>$stamp]; $this->insert('products',$products);
        $productIds=DB::table('products')->where('sku','like','DEMO-%')->pluck('id')->all(); $offers=[]; foreach(range(1,9000*$scale) as $n)$offers[]=['product_id'=>$productIds[$n%count($productIds)],'store_id'=>$storeIds[$n%count($storeIds)],'price_cents'=>100+($n%90000),'currency'=>['USD','EUR','AZN'][$n%3],'moq_milli'=>1000,'stock_milli'=>$n%17===0?0:(10+$n%1000)*1000,'lead_days'=>1+$n%14,'status'=>$n%19===0?'paused':'active','created_at'=>$stamp,'updated_at'=>$stamp]; $this->insert('offers',$offers);
        $offerRows=DB::table('offers')->whereIn('product_id',$productIds)->where('stock_milli','>',0)->get(); $orders=[];$items=[];$tasks=[]; foreach(range(1,3000*$scale) as $n){$offer=$offerRows[($n-1)%$offerRows->count()];$qty=1000;$total=$offer->price_cents;$status=['placed','confirmed','ready','delivered','cancelled'][$n%5];$orders[]=['number'=>sprintf('MO-%d-D%05d',$now->year,$n),'buyer_id'=>$buyers[$n%39],'supplier_id'=>null,'store_id'=>$offer->store_id,'rfq_id'=>null,'status'=>$status,'currency'=>$offer->currency,'total_cents'=>$total,'deliver_to'=>'Demo delivery point '.($n%20),'note'=>'Invented market order','cancel_reason'=>$status==='cancelled'?'Demo cancellation':null,'confirmed_at'=>in_array($status,['confirmed','ready','delivered'],true)?$stamp:null,'ready_at'=>in_array($status,['ready','delivered'],true)?$stamp:null,'delivered_at'=>$status==='delivered'?$stamp:null,'cancelled_at'=>$status==='cancelled'?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp];}
        $this->insert('market_orders',$orders); $marketOrders=DB::table('market_orders')->where('number','like','MO-'.$now->year.'-D%')->get(); foreach($marketOrders as $i=>$order){$offer=$offerRows[$i%$offerRows->count()];$product=DB::table('products')->find($offer->product_id);$items[]=['market_order_id'=>$order->id,'offer_id'=>$offer->id,'product_id'=>$offer->product_id,'rfq_item_id'=>null,'name'=>$product->name,'sku'=>$product->sku,'unit'=>$product->unit,'quantity_milli'=>1000,'unit_price_cents'=>$offer->price_cents,'total_cents'=>$offer->price_cents,'created_at'=>$stamp,'updated_at'=>$stamp]; foreach(['pick','deliver'] as $type)$tasks[]=['market_order_id'=>$order->id,'store_id'=>$order->store_id,'type'=>$type,'status'=>$order->status==='delivered'?'done':($order->status==='cancelled'?'cancelled':'open'),'assigned_to'=>null,'done_by'=>null,'done_at'=>$order->status==='delivered'?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp];} $this->insert('market_order_items',$items);$this->insert('worker_tasks',$tasks);
    }
}
