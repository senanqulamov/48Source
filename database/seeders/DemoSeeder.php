<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AwardService;
use App\Services\PurchaseOrderService;
use App\Services\QuoteService;
use App\Services\RfqService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Invented demo data for local use. Every name, company and figure is made up.
 * All demo accounts share one password: DEMO_PASSWORD from the environment if set,
 * otherwise a random one that is printed once when the seeder runs.
 */
class DemoSeeder extends Seeder
{
    public function run(RfqService $rfqs, QuoteService $quotes, AwardService $awards, PurchaseOrderService $orders): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must not run on production.');
        }
        $this->call(DatabaseSeeder::class);
        $password = env('DEMO_PASSWORD') ?: Str::password(14, symbols: false);
        $cat = fn (string $name) => Category::where('name', $name)->value('id');
        $user = fn (string $name, string $email, string $role, array $extra = []) => User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'role' => $role, 'status' => 'active', 'email_verified_at' => now()] + $extra);

        $admin = $user('Helena Voss', 'admin@demo.48source.test', 'admin', ['department' => 'Procurement']);
        $buyer = $user('Daniel Okafor', 'buyer@demo.48source.test', 'buyer', ['department' => 'Construction']);
        $buyer2 = $user('Sofia Marchetti', 'buyer2@demo.48source.test', 'buyer', ['department' => 'IT']);
        $user('Rui Mendes', 'seller@demo.48source.test', 'seller');
        $user('Ilyas Demir', 'worker@demo.48source.test', 'market_worker');

        $sup = [];
        foreach ([
            ['Baltic Steel & Rebar', 'Steel & metals', 'Riga', 45, 'active', 'supplier@demo.48source.test', 'Marta Kovac'],
            ['Northfield Metals', 'Steel & metals', 'Gdansk', 42, 'active', 'supplier2@demo.48source.test', 'Piotr Nowak'],
            ['Graywacke Cement & Aggregates', 'Construction materials', 'Leeds', 38, 'active', 'supplier3@demo.48source.test', 'Chen Wei'],
            ['Vantage IT Supply', 'IT hardware', 'Dublin', 47, 'active', 'supplier4@demo.48source.test', 'Priya Nair'],
            ['Novagrid Engineering', 'Industrial equipment', 'Oslo', 40, 'pending', 'supplier5@demo.48source.test', 'Kamran Aliyev'],
            ['Halden Freight', 'Logistics services', 'Hamburg', 30, 'blocked', null, 'Jon Halden'],
        ] as [$name, $category, $city, $rating, $status, $email, $contact]) {
            $s = Supplier::updateOrCreate(['name' => $name], ['category_id' => $cat($category), 'city' => $city, 'rating_tenths' => $rating, 'status' => $status, 'contact_name' => $contact, 'email' => $email, 'approved_at' => $status === 'active' ? now()->subMonths(6) : null, 'status_reason' => $status === 'blocked' ? 'Repeated late deliveries in 2025.' : null]);
            if ($email) {
                $user($contact, $email, 'supplier', ['supplier_id' => $s->id]);
            }
            $sup[$name] = $s;
        }
        $user('Jonas Petersen', 'fieldworker@demo.48source.test', 'supplier_worker', ['supplier_id' => $sup['Baltic Steel & Rebar']->id]);

        // Marketplace: markets, stores, their people, products and offers (all invented).
        $mk = fn (string $name, string $city) => \App\Models\Market::firstOrCreate(['name' => $name], ['city' => $city]);
        $st = fn (string $name, \App\Models\Market $m, string $stall) => \App\Models\Store::firstOrCreate(['name' => $name], ['market_id' => $m->id, 'stall' => $stall]);
        $central = $mk('Central Building Market', 'Riga');
        $harbour = $mk('Harbour Trade Yard', 'Gdansk');
        $stores = ['Mendes Steel Trading' => $st('Mendes Steel Trading', $central, 'A-14'), 'Ostrava Metal Stock' => $st('Ostrava Metal Stock', $central, 'B-03'), 'Harbour Mesh & Wire' => $st('Harbour Mesh & Wire', $harbour, '21'), 'Bright Site Supplies' => $st('Bright Site Supplies', $harbour, '7')];
        User::whereIn('email', ['seller@demo.48source.test', 'worker@demo.48source.test'])->update(['store_id' => $stores['Mendes Steel Trading']->id]);
        $seller2 = $user('Lena Vasquez', 'seller2@demo.48source.test', 'seller');
        User::where('email', 'seller2@demo.48source.test')->update(['store_id' => $stores['Harbour Mesh & Wire']->id]);
        if (\App\Models\Offer::count() === 0) {
            $sellerService = app(\App\Services\SellerService::class);
            foreach ([
                ['RB-16', 'Rebar B500B 16 mm, 12 m', 't', 'Steel & metals', ['Mendes Steel Trading' => ['688', '5', '220', 3], 'Ostrava Metal Stock' => ['679.50', '20', '400', 6], 'Harbour Mesh & Wire' => ['694', '1', '60', 2]]],
                ['RB-25', 'Rebar B500B 25 mm, 12 m', 't', 'Steel & metals', ['Mendes Steel Trading' => ['706', '5', '150', 3], 'Ostrava Metal Stock' => ['699', '20', '90', 6]]],
                ['WM-A393', 'Welded mesh A393, 4.8 x 2.4 m', 'pcs', 'Steel & metals', ['Harbour Mesh & Wire' => ['63.40', '50', '2400', 2], 'Mendes Steel Trading' => ['66', '10', '900', 3]]],
                ['WM-A252', 'Welded mesh A252, 4.8 x 2.4 m', 'pcs', 'Steel & metals', ['Harbour Mesh & Wire' => ['45.90', '50', '1800', 2]]],
                ['TW-16', 'Tie wire 1.6 mm, 20 kg coil', 'pcs', 'Steel & metals', ['Harbour Mesh & Wire' => ['31.20', '5', '340', 1], 'Bright Site Supplies' => ['33', '1', '80', 1]]],
                ['SS-SET', 'Safety sign set, site entrance', 'pcs', 'Construction materials', ['Bright Site Supplies' => ['38.50', '10', '260', 2]]],
                ['CM-42', 'Cement CEM I 42.5 R, 25 kg bag', 'bag', 'Construction materials', ['Bright Site Supplies' => ['6.85', '40', '5200', 1]]],
                ['HV-VEST', 'High-visibility vest, class 2', 'pcs', 'Construction materials', ['Bright Site Supplies' => ['7.40', '20', '1500', 1]]],
            ] as [$sku, $name, $unit, $category, $offers]) {
                foreach ($offers as $storeName => [$price, $moq, $stock, $lead]) {
                    $sellerService->saveOffer($stores[$storeName], ['sku' => $sku, 'name' => $name, 'unit' => $unit, 'category' => $category, 'price' => $price, 'moq' => $moq, 'stock' => $stock, 'lead_days' => $lead]);
                }
            }
        }
        foreach (['Steel & metals' => 900000, 'Construction materials' => 250000, 'IT hardware' => 120000] as $category => $amount) {
            \App\Models\Budget::firstOrCreate(['year' => (int) now()->format('Y'), 'category_id' => $cat($category)], ['amount_cents' => $amount * 100]);
        }

        if (Rfq::count() === 0) {
            $steel = [['name' => 'Rebar B500B 16 mm, 12 m bars', 'quantity' => '144', 'unit' => 't', 'target_price' => '700'], ['name' => 'Rebar B500B 25 mm, 12 m bars', 'quantity' => '96', 'unit' => 't', 'target_price' => '720'], ['name' => 'Welded mesh A393, 4.8 x 2.4 m', 'quantity' => '820', 'unit' => 'pcs', 'target_price' => '70']];
            $base = ['description' => 'Demo request.', 'type' => 'invited', 'currency' => 'USD', 'required_by' => now()->addDays(45)->toDateString(), 'requirements' => 'Mill certificates EN 10204 3.1.', 'payment_terms' => 'Net 30', 'scoring_preset' => 'balanced', 'sealed' => false, 'delivery_location' => 'Eastgate Depot'];
            $steelSup = [$sup['Baltic Steel & Rebar']->id, $sup['Northfield Metals']->id, $sup['Graywacke Cement & Aggregates']->id];
            $price = fn (Rfq $r, array $p) => array_combine($r->items->pluck('id')->all(), $p);
            $act = function (User $u, callable $fn) {
                Auth::setUser($u);

                return $fn();
            };
            $supUser = fn (Supplier $s) => User::where('supplier_id', $s->id)->where('role', 'supplier')->first();
            $quote = fn (Rfq $r, Supplier $s, array $p, int $days, string $terms = 'Net 30') => $act($supUser($s), fn () => $quotes->submit($r->load('items'), $s, $supUser($s), ['currency' => 'USD', 'delivery_days' => $days, 'payment_terms' => $terms, 'valid_until' => now()->addDays(30)->toDateString(), 'notes' => null, 'prices' => $price($r, $p)]));

            // 1. Open request with two quotations so far
            $r1 = $act($buyer, fn () => $rfqs->publish($rfqs->save($buyer, $base + ['title' => 'Reinforcement steel - Substation 2', 'category_id' => $cat('Steel & metals'), 'budget' => '320000', 'deadline_at' => now()->addDays(6), 'items' => $steel, 'suppliers' => $steelSup])));
            $quote($r1, $sup['Baltic Steel & Rebar'], ['699', '721', '68.50'], 14);
            $quote($r1, $sup['Northfield Metals'], ['705', '715', '71'], 10, 'Net 45');

            // 2. Closed request, ready to compare (one partial quotation)
            $r2 = $act($buyer, fn () => $rfqs->publish($rfqs->save($buyer, $base + ['title' => 'Structural steel - Bridge deck', 'category_id' => $cat('Steel & metals'), 'budget' => '300000', 'deadline_at' => now()->addDays(2), 'items' => $steel, 'suppliers' => $steelSup])));
            $quote($r2, $sup['Baltic Steel & Rebar'], ['690', '715', '69'], 12);
            $quote($r2, $sup['Northfield Metals'], ['702', '708', '66'], 9, 'Net 45');
            $quote($r2, $sup['Graywacke Cement & Aggregates'], ['', '', '64'], 20, 'Net 15');
            $act($buyer, fn () => $rfqs->close($r2));

            // 3. Awarded request with an order in transit
            $r3 = $act($buyer, fn () => $rfqs->publish($rfqs->save($buyer, $base + ['title' => 'Rebar top-up - Warehouse slab', 'category_id' => $cat('Steel & metals'), 'budget' => '120000', 'deadline_at' => now()->addDay(), 'items' => [$steel[0]], 'suppliers' => $steelSup])));
            $q3 = $quote($r3, $sup['Baltic Steel & Rebar'], ['695'], 10);
            $quote($r3, $sup['Northfield Metals'], ['712'], 8);
            $act($buyer, fn () => $rfqs->close($r3));
            $po = $act($buyer, fn () => $awards->award($r3->refresh(), $q3, $buyer));
            $act($buyer, fn () => $orders->advance($po, $buyer));
            $act($supUser($sup['Baltic Steel & Rebar']), fn () => $orders->advance($po->refresh(), $supUser($sup['Baltic Steel & Rebar'])));
            $act($supUser($sup['Baltic Steel & Rebar']), fn () => $orders->advance($po->refresh(), $supUser($sup['Baltic Steel & Rebar'])));

            // 4. A second buyer's request, and a draft
            $act($buyer2, fn () => $rfqs->publish($rfqs->save($buyer2, $base + ['title' => 'Developer laptops - Q1 intake', 'category_id' => $cat('IT hardware'), 'budget' => '90000', 'deadline_at' => now()->addDays(9), 'delivery_location' => 'Head office', 'items' => [['name' => 'Laptop 14 inch, 32 GB RAM', 'quantity' => '48', 'unit' => 'pcs', 'target_price' => '1800']], 'suppliers' => [$sup['Vantage IT Supply']->id]])));
            $act($buyer, fn () => $rfqs->save($buyer, $base + ['title' => 'Safety signage - Eastgate Depot', 'category_id' => $cat('Construction materials'), 'budget' => '', 'deadline_at' => now()->addDays(14), 'items' => [['name' => 'Safety sign set', 'quantity' => '60', 'unit' => 'pcs', 'target_price' => '40']], 'suppliers' => []]));

            // ---- Part 2 demo data (all invented) ----
            $baltic = $sup['Baltic Steel & Rebar'];
            $north = $sup['Northfield Metals'];
            $balticUser = $supUser($baltic);
            $req = app(\App\Services\RequisitionService::class);
            $pr1 = $act($buyer2, fn () => $req->submit($req->save($buyer2, ['title' => 'Site office furniture - Eastgate', 'justification' => 'Twelve new desks for the site team starting in January.', 'needed_by' => now()->addDays(30)->toDateString(), 'category_id' => $cat('Construction materials')],
                [['name' => 'Desk 160 cm', 'quantity' => '12', 'unit' => 'pcs', 'est_price' => '310'], ['name' => 'Task chair', 'quantity' => '12', 'unit' => 'pcs', 'est_price' => '185']]), $buyer2));
            $pr2 = $act($buyer, fn () => $req->submit($req->save($buyer, ['title' => 'Tower crane hire - Bridge deck', 'justification' => 'Needed for the deck pour sequence.', 'needed_by' => now()->addDays(60)->toDateString()],
                [['name' => 'Tower crane hire', 'quantity' => '6', 'unit' => 'months', 'est_price' => '9800']]), $buyer));
            $act($buyer2, fn () => $req->decide($pr2, $buyer2, true, 'Within the project budget.'));
            $act($buyer, fn () => $req->save($buyer, ['title' => 'Survey equipment calibration'], [['name' => 'Total station calibration', 'quantity' => '3', 'unit' => 'pcs', 'est_price' => '']]));

            $contracts = app(\App\Services\ContractService::class);
            $ct = $act($buyer, fn () => $contracts->activate($contracts->save($buyer, ['supplier_id' => $baltic->id, 'title' => 'Reinforcement steel framework 2027', 'ceiling' => '500000', 'starts_on' => now()->subMonth()->toDateString(), 'ends_on' => now()->addMonths(11)->toDateString(), 'payment_terms' => 'Net 30', 'terms' => 'Prices fixed for the period. Delivery within 14 days of each release order.'],
                [['name' => 'Rebar B500B 16 mm', 'unit' => 't', 'price' => '695'], ['name' => 'Rebar B500B 25 mm', 'unit' => 't', 'price' => '712'], ['name' => 'Welded mesh A393', 'unit' => 'pcs', 'price' => '67.50']])));
            $ro = $act($buyer, fn () => $contracts->release($ct->load('items'), $buyer, [$ct->items[0]->id => '40', $ct->items[2]->id => '200'], now()->addDays(14)->toDateString(), 'North abutment'));
            $act($balticUser, fn () => $contracts->moveRelease($ro, $balticUser, 'accepted'));
            $act($buyer, fn () => $contracts->save($buyer, ['supplier_id' => $north->id, 'title' => 'Structural sections call-off', 'ceiling' => '', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonths(6)->toDateString()], [['name' => 'HEB 300 beam, S355', 'unit' => 't', 'price' => '1210']]));

            // Receipt and invoice on the order in transit: 100 of 144 t received, 110 t invoiced, so the match finds a difference.
            $po->refresh()->load('items');
            $line = $po->items->first();
            $act($buyer, fn () => app(\App\Services\ReceiptService::class)->record($po, $buyer, [$line->id => '100'], [], now()->toDateString(), 'Delivery note DN-4471'));
            $act($balticUser, fn () => app(\App\Services\InvoiceService::class)->submit($po, $balticUser, ['supplier_ref' => 'BSR-2026-0918', 'issued_on' => now()->toDateString(), 'due_on' => now()->addDays(30)->toDateString()], [$line->id => ['quantity' => '110', 'price' => '695']]));

            $insight = app(\App\Services\SupplierInsightService::class);
            $act($buyer, function () use ($insight, $baltic, $north, $buyer) {
                $insight->addDocument($baltic, $buyer, ['type' => 'registration', 'title' => 'Company registration extract']);
                $insight->addDocument($baltic, $buyer, ['type' => 'iso', 'title' => 'ISO 9001 certificate', 'expires_on' => now()->addDays(20)->toDateString()]);
                $insight->addDocument($north, $buyer, ['type' => 'insurance', 'title' => 'Liability insurance', 'expires_on' => now()->subDays(12)->toDateString()]);
                $q = now()->subQuarter();
                $insight->score($baltic, $buyer, $q->format('Y') . '-Q' . $q->quarter, ['quality' => 5, 'delivery' => 4, 'price' => 4, 'service' => 5], 'Reliable. One late truck in the quarter.');
                $insight->score($north, $buyer, $q->format('Y') . '-Q' . $q->quarter, ['quality' => 4, 'delivery' => 3, 'price' => 5, 'service' => 3]);
                $insight->addAction($north, $buyer, 'Send the renewed liability insurance', now()->addDays(10)->toDateString());
            });
            $act($balticUser, fn () => $insight->addDocument($baltic, $balticUser, ['type' => 'tax', 'title' => 'Tax clearance certificate', 'expires_on' => now()->addMonths(8)->toDateString()]));

            $fa = $act($buyer, fn () => app(\App\Services\AssessmentService::class)->request($buyer, $baltic, 'Site access and unloading survey', \App\Models\FieldAssessment::DEFAULT_CHECKLIST, $r1, User::where('email', 'fieldworker@demo.48source.test')->value('id'), now()->addDays(4)->toDateString()));

            $rfis = app(\App\Services\RfiService::class);
            $rfi = $act($buyer, fn () => $rfis->publish($rfis->save($buyer, ['title' => 'Market sounding - precast concrete elements', 'description' => 'We plan a tender for precast wall panels in the first quarter.', 'deadline_at' => now()->addDays(8)],
                ['What is your usual lead time for 200 wall panels?', 'Which quality certificates do you hold?', 'Can you deliver to site in sequence?'], [$baltic->id, $north->id, $sup['Graywacke Cement & Aggregates']->id])->load('responses')));
            $graywackeUser = $supUser($sup['Graywacke Cement & Aggregates']);
            $act($graywackeUser, fn () => $rfis->answer($rfi, $graywackeUser, ['Five to six weeks from approved drawings.', 'ISO 9001 and CE marking to EN 14992.', 'Yes, by pour sequence with 48 hours notice.']));

            $qa = app(\App\Services\QaService::class);
            $question = $act($balticUser, fn () => $qa->ask($r1, $balticUser, 'Can the rebar be delivered cut and bent, or straight bars only?'));
            $act($buyer, fn () => $qa->answer($question, $buyer, 'Straight 12 m bars only. Cutting and bending is done on site.'));
            $northUser = $supUser($north);
            $act($northUser, fn () => $qa->ask($r1, $northUser, 'Is partial delivery in two lots acceptable?'));

            $msg = app(\App\Services\MessageService::class);
            $conv = $act($buyer, fn () => $msg->start($buyer, $baltic, 'Delivery window for ' . $po->number, 'Can the remaining 44 t arrive before Friday? The pour is planned for Monday.', $po));
            $act($balticUser, fn () => $msg->post($conv, $balticUser, 'Yes. Two trucks are booked for Thursday morning.'));

            // A marketplace purchase by the supplier, sourced for the open request, with the first store already picking.
            $market = app(\App\Services\MarketService::class);
            $act($balticUser, function () use ($market, $balticUser, $r1) {
                $market->addCheapestMix($balticUser, $r1->load('items'));
                $orders = $market->checkout($balticUser, 'Baltic Steel yard, gate 2', 'Call before delivery');
                $seller = User::where('email', 'seller@demo.48source.test')->first();
                $own = $orders->firstWhere('store_id', $seller->store_id);
                if ($own) {
                    Auth::setUser($seller);
                    $market->advance($own, $seller);
                }
            });
            Auth::logout();
        }

        $this->command?->newLine();
        $this->command?->info('Demo data loaded. All demo accounts use this password: '.$password);
        $this->command?->table(['Role', 'Email'], [['Administrator', $admin->email], ['Buyer', $buyer->email], ['Buyer (second)', $buyer2->email], ['Supplier', 'supplier@demo.48source.test'], ['Supplier (second)', 'supplier2@demo.48source.test'], ['Supplier (pending approval)', 'supplier5@demo.48source.test']]);
    }
}
