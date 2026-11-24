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

    public function run(int $scale = 1, int $seed = 48, bool $fresh = false): void
    {
        if (app()->isProduction()) throw new RuntimeException('Demo data must never run in production.');
        if ($fresh) $this->clear();
        if (DB::table('demo_data_runs')->exists()) { $this->line('Large demo data already exists - use --fresh to rebuild it.'); return; }
        mt_srand($seed); $started = microtime(true); $this->call(DatabaseSeeder::class);
        $password = env('DEMO_PASSWORD') ?: Str::password(18, symbols: true);
        $now = now(); $stamp = $now->toDateTimeString(); $passwordHash = Hash::make($password);
        $this->line('1/6 Categories, suppliers and accounts');

        $groups = ['Construction', 'Metals', 'Technology', 'Facilities', 'Logistics', 'Industrial', 'Safety', 'Professional'];
        foreach ($groups as $g) {
            $gid = DB::table('categories')->insertGetId(['name' => self::PREFIX.$g, 'slug' => 'demo-'.Str::slug($g), 'created_at' => $stamp, 'updated_at' => $stamp]);
            foreach (range(1, 5) as $n) DB::table('categories')->insert(['parent_id' => $gid, 'name' => self::PREFIX."$g category $n", 'slug' => "demo-".Str::slug($g)."-$n", 'created_at' => $stamp, 'updated_at' => $stamp]);
        }
        $categories = DB::table('categories')->where('slug', 'like', 'demo-%')->whereNotNull('parent_id')->pluck('id')->all();
        $admins = $this->users(array_map(fn ($n) => ['name' => "Demo Administrator $n", 'email' => "admin$n@demo.48source.test", 'role' => 'admin'], range(1, 3)), $passwordHash, $stamp);
        $buyerRows = array_map(fn ($n) => ['name' => $n === 40 ? 'Demo Buyer No Requests' : "Demo Buyer $n", 'email' => "bulk.buyer$n@demo.48source.test", 'role' => 'buyer', 'department' => ['Capital projects', 'IT', 'Operations'][$n % 3]], range(1, 40));
        $buyers = $this->users($buyerRows, $passwordHash, $stamp);
        $supplierRows = []; $names = ['Atlas', 'Caspian', 'Nordic', 'Orion', 'Vector', 'Meridian', 'Körpü', 'Şimal']; $cities = ['Baku', 'Ganja', 'Tbilisi', 'Riga', 'Ankara'];
        foreach (range(1, 1000 * $scale) as $n) { $status = ['active', 'pending', 'inactive', 'blocked'][intdiv($n - 1, max(1, 250 * $scale)) % 4]; $supplierRows[] = ['name' => self::PREFIX.$names[$n % count($names)]." Procurement $n", 'legal_name' => self::PREFIX."Trading $n LLC", 'category_id' => $categories[$n % count($categories)], 'city' => $cities[$n % count($cities)], 'country' => 'Inventedland', 'contact_name' => "Demo Contact $n", 'email' => "supplier$n@demo.48source.test", 'phone' => '+994 50 555 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'status' => $status, 'status_reason' => $status === 'blocked' ? 'Demo compliance hold.' : null, 'rating_tenths' => 25 + ($n % 26), 'approved_at' => $status === 'active' ? $now->copy()->subDays(30 + ($n % 400))->toDateTimeString() : null, 'approved_by' => $admins[0], 'created_at' => $stamp, 'updated_at' => $stamp]; }
        $this->insert('suppliers', $supplierRows); $supplierIds = DB::table('suppliers')->where('email', 'like', 'supplier%@demo.48source.test')->orderBy('id')->pluck('id')->all();
        $accountRows = []; foreach ($supplierIds as $i => $sid) { $n = $i + 1; $accountRows[] = ['name' => "Demo Supplier User $n", 'email' => "supplier.user$n@demo.48source.test", 'role' => 'supplier', 'supplier_id' => $sid]; if ($n % 3 === 0) $accountRows[] = ['name' => "Demo Field Worker $n", 'email' => "field$n@demo.48source.test", 'role' => 'supplier_worker', 'supplier_id' => $sid]; }
        $this->users($accountRows, $passwordHash, $stamp); $supplierUsers = DB::table('users')->where('email', 'like', 'supplier.user%@demo.48source.test')->pluck('id', 'supplier_id')->all();

        $this->line('2/6 RFQs, invitations, quotations and orders');
        $terms = ['Net 15', 'Net 30', 'Net 45']; $rfqs = []; $rfqCount = 2000 * $scale;
        foreach (range(1, $rfqCount) as $n) { $status = $n <= 1125 * $scale ? 'awarded' : ['draft', 'open', 'closed', 'cancelled'][$n % 4]; $when = $now->copy()->subDays(mt_rand(1, 540)); $rfqs[] = ['number' => sprintf('RFQ-%d-D%05d', $now->year, $n), 'title' => self::PREFIX.($n === 1 ? str_repeat('Long construction procurement title ', 6) : ['Steel reinforcement', 'Industrial pump', 'Network hardware', 'Facility cleaning', 'Freight transport'][$n % 5])." request $n", 'description' => 'Invented procurement request for realistic searching and reporting.', 'type' => $n % 4 === 0 ? 'public' : 'invited', 'category_id' => $categories[$n % count($categories)], 'buyer_id' => $buyers[$n % 39], 'status' => $status, 'currency' => ['USD', 'EUR', 'AZN'][$n % 3], 'budget_cents' => $n % 97 === 0 ? 0 : (50000 + ($n % 700) * 10000), 'deadline_at' => $when->copy()->addDays(10)->toDateTimeString(), 'delivery_location' => 'Demo depot '.($n % 12 + 1), 'required_by' => $when->copy()->addDays(45)->toDateString(), 'requirements' => 'Demo specification and quality certificates.', 'payment_terms' => $terms[$n % 3], 'scoring_preset' => 'balanced', 'sealed' => $n % 7 === 0, 'round' => 1, 'published_at' => $status === 'draft' ? null : $when->toDateTimeString(), 'closed_at' => in_array($status, ['closed', 'awarded'], true) ? $when->copy()->addDays(10)->toDateTimeString() : null, 'awarded_at' => $status === 'awarded' ? $when->copy()->addDays(12)->toDateTimeString() : null, 'cancelled_at' => $status === 'cancelled' ? $when->copy()->addDays(3)->toDateTimeString() : null, 'cancel_reason' => $status === 'cancelled' ? 'Demo scope changed.' : null, 'created_at' => $when->toDateTimeString(), 'updated_at' => $when->toDateTimeString()]; }
        $this->insert('rfqs', $rfqs); $rfqIds = DB::table('rfqs')->where('title', 'like', self::PREFIX.'%')->orderBy('id')->pluck('id')->all();
        $items = []; foreach ($rfqIds as $i => $id) { $lines = $i === 0 ? 25 : ($i === 1 ? 1 : 1 + ($i % 8)); for ($p = 1; $p <= $lines; $p++) $items[] = ['rfq_id' => $id, 'position' => $p, 'name' => ['Rebar B500B', 'Industrial valve', 'Laptop workstation', 'Safety helmet', 'Freight service'][($i + $p) % 5]." $p", 'description' => 'Invented line item.', 'quantity_milli' => (10 + (($i + $p) % 200)) * 1000, 'unit' => 'pcs', 'target_price_cents' => 1000 + (($i + $p) % 9000), 'created_at' => $stamp, 'updated_at' => $stamp]; }
        $this->insert('rfq_items', $items); $firstItems = DB::table('rfq_items')->selectRaw('MIN(id) id, rfq_id')->whereIn('rfq_id', $rfqIds)->groupBy('rfq_id')->pluck('id', 'rfq_id')->all();
        $invitations = []; $quotes = []; foreach ($rfqIds as $i => $rid) { $quoteN = $i % 5 === 0 ? 0 : 5; for ($q = 0; $q < max(3, $quoteN); $q++) { $sid = $supplierIds[($i * 7 + $q) % (250 * $scale)]; $invitations[] = ['rfq_id' => $rid, 'supplier_id' => $sid, 'status' => $q < $quoteN ? 'accepted' : 'pending', 'decline_reason' => null, 'responded_at' => $stamp, 'created_at' => $stamp, 'updated_at' => $stamp]; if ($q < $quoteN) $quotes[] = ['rfq_id' => $rid, 'supplier_id' => $sid, 'submitted_by' => $supplierUsers[$sid] ?? null, 'status' => $i < 1125 * $scale && $q === 0 ? 'won' : ($q === 3 && $i % 9 === 0 ? 'withdrawn' : 'submitted'), 'currency' => ['USD','EUR','AZN'][$i % 3], 'delivery_days' => 3 + ($q * 2), 'payment_terms' => $terms[$q % 3], 'valid_until' => $now->copy()->addDays($i % 11 === 0 ? -1 : 30)->toDateString(), 'notes' => 'Invented quotation.', 'total_cents' => 100000 + $i * 23 + $q * 1000, 'revision' => $q === 2 && $i % 7 === 0 ? 1 : 0, 'submitted_at' => $stamp, 'created_at' => $stamp, 'updated_at' => $stamp]; } }
        $this->insert('rfq_invitations', $invitations); $this->insert('quotes', $quotes);
        $quoteRows = DB::table('quotes')->whereIn('rfq_id', $rfqIds)->get(['id','rfq_id','total_cents']); $quoteItems = []; foreach ($quoteRows as $q) $quoteItems[] = ['quote_id' => $q->id, 'rfq_item_id' => $firstItems[$q->rfq_id], 'unit_price_cents' => $q->total_cents, 'total_cents' => $q->total_cents, 'created_at' => $stamp, 'updated_at' => $stamp]; $this->insert('quote_items', $quoteItems);
        $this->makeOrders($rfqIds, $supplierIds, $buyers, $firstItems, $supplierUsers, $stamp, $now, $scale);

        $this->line('3/6 Requisitions, contracts, supplier records and conversations');
        $this->supportingRecords($categories, $buyers, $supplierIds, $supplierUsers, $rfqIds, $stamp, $now, $scale);
        $this->line('4/6 Markets, products, offers and marketplace orders');
        $this->marketplace($categories, $buyers, $passwordHash, $stamp, $now, $scale);
        $this->line('5/6 Reports, notifications and sequences');
        foreach ($categories as $category) foreach ([$now->year - 1, $now->year] as $year) DB::table('budgets')->updateOrInsert(['year' => $year, 'category_id' => $category], ['amount_cents' => $category % 13 === 0 ? 0 : 10000000 + $category * 1000, 'created_at' => $stamp, 'updated_at' => $stamp]);
        DB::table('activity_logs')->insert(['user_id' => $buyers[0], 'actor_name' => 'Demo Buyer 1', 'area' => 'RFQs', 'action' => 'Created demo data', 'subject_type' => null, 'subject_id' => null, 'subject_label' => 'Large demo dataset', 'supplier_id' => null, 'meta' => json_encode(['seed' => $seed]), 'created_at' => $stamp]);
        $this->syncSequences($rfqCount, 900 * $scale); DB::table('demo_data_runs')->insert(['seed' => $seed, 'scale' => $scale, 'password_hint' => substr($password, 0, 2).'...', 'created_at' => $stamp, 'updated_at' => $stamp]);
        $this->line('6/6 Done in '.number_format(microtime(true) - $started, 1).' seconds.');
        $this->table(['Table', 'Rows'], collect(['suppliers','users','rfqs','rfq_items','quotes','purchase_orders','products','offers','market_orders'])->map(fn ($t) => [$t, DB::table($t)->count()])->all());
        $this->info('All demo accounts use this password: '.$password); $this->table(['Role', 'Email'], [['Administrator', 'admin1@demo.48source.test'], ['Buyer', 'bulk.buyer1@demo.48source.test'], ['Supplier', 'supplier.user1@demo.48source.test']]);
    }

    private function users(array $rows, string $hash, string $stamp): array { foreach ($rows as &$r) $r += ['password' => $hash, 'status' => 'active', 'email_verified_at' => $stamp, 'theme' => 'light', 'locale' => 'en', 'two_factor' => false, 'email_notices' => true, 'created_at' => $stamp, 'updated_at' => $stamp]; unset($r); $this->insert('users', $rows); return DB::table('users')->whereIn('email', array_column($rows, 'email'))->orderBy('id')->pluck('id')->all(); }
    private function insert(string $table, array $rows): void { foreach (array_chunk($rows, 500) as $chunk) DB::table($table)->insert($chunk); }
    private function line(string $text): void { $this->command?->info($text); }
    private function table(array $head, array $rows): void { $this->command?->table($head, $rows); }
    private function info(string $text): void { $this->command?->info($text); }
    private function syncSequences(int $rfqs, int $orders): void { foreach (['RFQ' => $rfqs, 'PO' => $orders, 'REQ' => 400, 'CON' => 120, 'INV' => 1200, 'RFI' => 150, 'MO' => 3000] as $name => $value) DB::table('sequences')->updateOrInsert(['name' => $name, 'year' => now()->year], ['value' => $value]); }
    private function clear(): void
    {
        // The small DemoSeeder uses the same safe domain, so never delete by email
        // alone. Every row made here has the explicit "Demo " label or DEMO- SKU.
        DB::transaction(function () {
            DB::table('demo_data_runs')->delete();
            $rfqIds = DB::table('rfqs')->where('title', 'like', self::PREFIX.'%')->select('id');
            DB::table('purchase_orders')->whereIn('rfq_id', $rfqIds)->delete();
            DB::table('rfqs')->where('title', 'like', self::PREFIX.'%')->delete();
            DB::table('requisitions')->where('title', 'like', self::PREFIX.'%')->delete();
            DB::table('offers')->whereIn('product_id', DB::table('products')->where('sku', 'like', 'DEMO-%')->select('id'))->delete();
            DB::table('products')->where('sku', 'like', 'DEMO-%')->delete();
            DB::table('stores')->where('name', 'like', self::PREFIX.'%')->delete();
            DB::table('markets')->where('name', 'like', self::PREFIX.'%')->delete();
            DB::table('suppliers')->where('name', 'like', self::PREFIX.'%')->delete();
            DB::table('users')->where('name', 'like', self::PREFIX.'%')->delete();
            DB::table('categories')->where('slug', 'like', 'demo-%')->delete();
        });
    }
    private function makeOrders(array $rfqs, array $suppliers, array $buyers, array $firstItems, array $supplierUsers, string $stamp, $now, int $scale): void { $orders=[]; $quotes=DB::table('quotes')->whereIn('rfq_id',$rfqs)->orderBy('rfq_id')->orderBy('id')->get()->unique('rfq_id')->take(900*$scale)->values(); foreach ($quotes as $i => $quote) { $status=['draft','issued','acknowledged','in_transit','delivered','invoiced','cancelled'][$i%7]; $orders[]=['number'=>sprintf('PO-%d-D%05d',$now->year,$i+1),'rfq_id'=>$quote->rfq_id,'supplier_id'=>$quote->supplier_id,'quote_id'=>$quote->id,'buyer_id'=>$buyers[$i%39],'status'=>$status,'currency'=>$quote->currency,'total_cents'=>$quote->total_cents,'payment_terms'=>'Net 30','delivery_location'=>'Demo depot','expected_at'=>$now->copy()->addDays(10)->toDateString(),'award_justification'=>'Lowest compliant demo quotation.','issued_at'=>$stamp,'acknowledged_at'=>in_array($status,['acknowledged','in_transit','delivered','invoiced'],true)?$stamp:null,'shipped_at'=>in_array($status,['in_transit','delivered','invoiced'],true)?$stamp:null,'delivered_at'=>in_array($status,['delivered','invoiced'],true)?$stamp:null,'invoiced_at'=>$status==='invoiced'?$stamp:null,'cancelled_at'=>$status==='cancelled'?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp]; } $this->insert('purchase_orders',$orders); }
    private function supportingRecords(array $categories, array $buyers, array $suppliers, array $supplierUsers, array $rfqs, string $stamp, $now, int $scale): void { /* Kept deliberately compact: list pages need representative state coverage. */ foreach (range(1, 400*$scale) as $n) DB::table('requisitions')->insert(['number'=>sprintf('REQ-%d-D%04d',$now->year,$n),'requester_id'=>$buyers[$n%39],'category_id'=>$categories[$n%count($categories)],'rfq_id'=>null,'title'=>self::PREFIX." requisition $n",'justification'=>'Invented business need.','needed_by'=>$now->copy()->addDays($n%90)->toDateString(),'currency'=>'USD','total_cents'=>100000+$n*100,'status'=>['draft','pending','approved','rejected','cancelled'][$n%5],'submitted_at'=>$stamp,'decided_at'=>$n%5>1?$stamp:null,'created_at'=>$stamp,'updated_at'=>$stamp]); }
    private function marketplace(array $categories, array $buyers, string $hash, string $stamp, $now, int $scale): void { $markets=[]; foreach(range(1,12*$scale) as $n)$markets[]=['name'=>self::PREFIX." Market $n",'city'=>'Demo City '.$n,'address'=>'Demo avenue '.$n,'status'=>'active','created_at'=>$stamp,'updated_at'=>$stamp]; $this->insert('markets',$markets); $marketIds=DB::table('markets')->where('name','like',self::PREFIX.'%')->pluck('id')->all(); $stores=[]; foreach(range(1,90*$scale) as $n)$stores[]=['market_id'=>$marketIds[$n%count($marketIds)],'name'=>self::PREFIX." Store $n",'stall'=>'D-'.$n,'phone'=>null,'status'=>$n%13===0?'paused':'active','created_at'=>$stamp,'updated_at'=>$stamp]; $this->insert('stores',$stores); $storeIds=DB::table('stores')->where('name','like',self::PREFIX.'%')->pluck('id')->all(); $products=[]; foreach(range(1,2500*$scale) as $n)$products[]=['sku'=>'DEMO-'.str_pad((string)$n,5,'0',STR_PAD_LEFT),'name'=>['Steel plate','Network switch','Safety glove','Pump seal','Freight pallet'][$n%5]." $n",'category_id'=>$categories[$n%count($categories)],'unit'=>'pcs','description'=>'Invented catalogue product.','status'=>'active','created_at'=>$stamp,'updated_at'=>$stamp]; $this->insert('products',$products); $productIds=DB::table('products')->where('sku','like','DEMO-%')->pluck('id')->all(); $offers=[]; foreach(range(1,9000*$scale) as $n)$offers[]=['product_id'=>$productIds[$n%count($productIds)],'store_id'=>$storeIds[$n%count($storeIds)],'price_cents'=>100+($n%90000),'currency'=>['USD','EUR','AZN'][$n%3],'moq_milli'=>1000,'stock_milli'=>$n%17===0?0:(10+$n%1000)*1000,'lead_days'=>1+$n%14,'status'=>$n%19===0?'paused':'active','created_at'=>$stamp,'updated_at'=>$stamp]; $this->insert('offers',$offers); }
}
