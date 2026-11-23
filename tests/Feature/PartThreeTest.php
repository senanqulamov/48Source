<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Accounting;
use App\Livewire\Market\Basket;
use App\Livewire\Market\Offers;
use App\Livewire\Market\Shop;
use App\Livewire\Market\Tasks;
use App\Livewire\Reports;
use App\Livewire\Rfqs\Index as RfqIndex;
use App\Models\Category;
use App\Models\FeatureFlag;
use App\Models\Market;
use App\Models\MarketOrder;
use App\Models\Offer;
use App\Models\OutboxEmail;
use App\Models\Store;
use App\Models\User;
use App\Services\AwardService;
use App\Services\InvoiceService;
use App\Services\MarketService;
use App\Services\PurchaseOrderService;
use App\Services\ReceiptService;
use App\Services\ReminderService;
use App\Services\ReportService;
use App\Services\SellerService;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PartThreeTest extends TestCase
{
    use RefreshDatabase;

    private function refuses(callable $fn, string $needle): void
    {
        try {
            $fn();
            $this->fail("Expected a refusal containing: {$needle}");
        } catch (BusinessRule $e) {
            $this->assertStringContainsString($needle, $e->getMessage());
        }
    }

    /** A store with a seller and a worker. */
    private function store(string $name = 'Alpha Store'): array
    {
        $store = Store::create(['name' => $name, 'market_id' => Market::firstOrCreate(['name' => 'Central Market'])->id])->fresh();
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id])->fresh();
        $worker = User::factory()->create(['role' => 'market_worker', 'store_id' => $store->id])->fresh();

        return [$store, $seller, $worker];
    }

    private function offer(Store $store, string $sku, string $name, string $unit, string $price, string $stock = '100', string $moq = '1'): Offer
    {
        return app(SellerService::class)->saveOffer($store, compact('sku', 'name', 'unit', 'price', 'stock', 'moq'));
    }

    public function test_two_stores_can_sell_the_same_product_at_different_prices(): void
    {
        [$a] = $this->store('Alpha Store');
        [$b] = $this->store('Beta Store');
        $x = $this->offer($a, 'RB-16', 'Rebar 16 mm', 't', '700.00');
        $y = $this->offer($b, 'rb-16', 'ignored for an existing product', 't', '690.50');
        $this->assertSame($x->product_id, $y->product_id);
        $this->assertSame('Rebar 16 mm', $y->product->name);
        $this->refuses(fn () => $this->offer($a, 'NEW-1', '', 't', '5'), 'needs a name');
        $this->refuses(fn () => $this->offer($a, 'RB-16', 'x', 't', '0'), 'above zero');

        $buyer = $this->buyer();
        Livewire::actingAs($buyer)->test(Shop::class)->assertSee('Rebar 16 mm')->assertSee('2 sellers')->assertSee('690.50')->call('show', $x->product_id)->assertSee('Alpha Store')->assertSee('Beta Store')
            ->set('qty.' . $y->id, '3')->call('add', $y->id);
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'offer_id' => $y->id, 'quantity_milli' => 3000]);
    }

    public function test_the_basket_respects_smallest_order_and_stock(): void
    {
        [$a] = $this->store();
        $o = $this->offer($a, 'CM-1', 'Cement bag', 'bag', '6.85', '100', '40');
        $buyer = $this->buyer();
        $market = app(MarketService::class);
        $this->refuses(fn () => $market->add($buyer, $o, '10'), 'smallest order');
        $this->refuses(fn () => $market->add($buyer, $o, '101'), 'in stock');
        $this->refuses(fn () => $market->add($buyer, $o, 'abc'), 'not a number');
        $market->add($buyer, $o, '40');
        $market->add($buyer, $o, '50');
        $this->assertSame(1, $buyer->fresh()->canShop() ? \App\Models\CartItem::where('user_id', $buyer->id)->count() : 0);
        [, $seller] = $this->store('Other');
        $this->refuses(fn () => $market->add($seller, $o, '40'), 'cannot buy');
    }

    public function test_checkout_makes_one_order_per_store_with_exact_totals_and_takes_stock(): void
    {
        [$a, $sellerA] = $this->store('Alpha Store');
        [$b] = $this->store('Beta Store');
        $x = $this->offer($a, 'RB-16', 'Rebar 16 mm', 't', '699.99', '50');
        $y = $this->offer($b, 'WM-1', 'Mesh sheet', 'pcs', '63.40', '500', '50');
        $z = $this->offer($b, 'TW-1', 'Tie wire coil', 'pcs', '31.20', '20');
        $sup = $this->supplierUser();
        $market = app(MarketService::class);
        Auth::setUser($sup);
        $market->add($sup, $x, '2.5');
        $market->add($sup, $y, '120');
        $market->add($sup, $z, '3');
        $this->refuses(fn () => $market->checkout($sup, ''), 'where the goods');
        $orders = $market->checkout($sup, 'Yard gate 2');
        $this->assertCount(2, $orders);
        $byStore = $orders->keyBy('store_id');
        $this->assertSame(174998, $byStore[$a->id]->total_cents); // 699.99 x 2.5 = 1,749.975, rounded half up
        $this->assertSame(760800 + 9360, $byStore[$b->id]->total_cents);
        $this->assertSame($sup->supplier_id, $byStore[$a->id]->supplier_id);
        $this->assertSame(47500, $x->fresh()->stock_milli);
        $this->assertSame(0, \App\Models\CartItem::count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $sellerA->id]);
        $this->assertDatabaseCount('purchase_orders', 0); // marketplace orders never become purchase orders
    }

    public function test_checkout_fails_as_a_whole_when_stock_ran_out_meanwhile(): void
    {
        [$a] = $this->store('Alpha Store');
        [$b] = $this->store('Beta Store');
        $x = $this->offer($a, 'A-1', 'Thing one', 'pcs', '10', '5');
        $y = $this->offer($b, 'B-1', 'Thing two', 'pcs', '10', '5');
        $buyer = $this->buyer();
        $market = app(MarketService::class);
        $market->add($buyer, $x, '5');
        $market->add($buyer, $y, '5');
        $y->update(['stock_milli' => 2000]);
        $this->refuses(fn () => $market->checkout($buyer, 'Site'), 'in stock');
        $this->assertSame(0, MarketOrder::count());
        $this->assertSame(5000, $x->fresh()->stock_milli);
        $this->assertSame(2, \App\Models\CartItem::count());
    }

    public function test_the_store_confirms_picks_and_delivers_and_cancelling_gives_stock_back(): void
    {
        [$a, $seller, $worker] = $this->store();
        [, $otherSeller] = $this->store('Other');
        $x = $this->offer($a, 'A-1', 'Thing', 'pcs', '10', '20');
        $buyer = $this->buyer();
        $market = app(MarketService::class);
        Auth::setUser($buyer);
        $market->add($buyer, $x, '8');
        $order = $market->checkout($buyer, 'Site')->first();

        $this->assertNull($market->nextFor($order, $buyer));
        $this->assertNull($market->nextFor($order, $otherSeller));
        $this->assertNull($market->nextFor($order, $worker), 'only the seller confirms');
        Auth::setUser($seller);
        $market->advance($order, $seller);
        $this->assertDatabaseHas('worker_tasks', ['market_order_id' => $order->id, 'type' => 'pick', 'status' => 'open']);
        $this->refuses(fn () => $market->cancel($order->fresh(), $buyer, 'Changed my mind'), 'no longer');

        Livewire::actingAs($worker)->test(Tasks::class)->assertSee($order->number)->call('done', $order->tasks()->first()->id);
        $this->assertSame('ready', $order->fresh()->status);
        $this->assertDatabaseHas('worker_tasks', ['market_order_id' => $order->id, 'type' => 'deliver', 'status' => 'open']);
        Auth::setUser($worker);
        $market->advance($order->fresh(), $worker);
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(0, \App\Models\WorkerTask::where('status', 'open')->count());

        // A second order, cancelled by the buyer before confirmation.
        Auth::setUser($buyer);
        $market->add($buyer, $x->fresh(), '5');
        $second = $market->checkout($buyer, 'Site')->first();
        $this->assertSame(7000, $x->fresh()->stock_milli);
        $market->cancel($second, $buyer, 'Ordered twice');
        $this->assertSame(12000, $x->fresh()->stock_milli);
    }

    public function test_marketplace_orders_are_private_to_the_buyer_and_the_store(): void
    {
        [$a, $seller] = $this->store();
        [, $otherSeller] = $this->store('Other');
        $x = $this->offer($a, 'A-1', 'Thing', 'pcs', '10', '20');
        $sup = $this->supplierUser();
        Auth::setUser($sup);
        app(MarketService::class)->add($sup, $x, '2');
        $order = app(MarketService::class)->checkout($sup, 'Yard')->first();
        $this->actingAs($sup)->get(route('market.orders.show', $order))->assertOk();
        $this->actingAs($seller)->get(route('market.orders.show', $order))->assertOk()->assertSee('Confirm order');
        $this->actingAs($otherSeller)->get(route('market.orders.show', $order))->assertNotFound();
        $this->actingAs($this->supplierUser())->get(route('market.orders.show', $order))->assertNotFound();
        $this->actingAs($this->buyer())->get(route('market.orders.show', $order))->assertNotFound();
        $pdf = $this->actingAs($sup)->get(route('market.orders.pdf', $order));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($otherSeller)->get(route('market.orders.pdf', $order))->assertNotFound();
    }

    public function test_sourcing_matches_each_request_item_by_words_size_and_unit(): void
    {
        [$a] = $this->store('Alpha Store');
        [$b] = $this->store('Beta Store');
        $this->offer($a, 'RB-16', 'Rebar B500B 16 mm, 12 m', 't', '690', '500');
        $cheap = $this->offer($b, 'RB-16', 'x', 't', '680', '500', '20');
        $this->offer($a, 'RB-25', 'Rebar B500B 25 mm, 12 m', 't', '600', '500');
        $this->offer($a, 'RB-16-KG', 'Rebar B500B 16 mm per kilo', 'kg', '0.50', '500');
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$sup]);
        $item = $rfq->items[0];
        $item->update(['name' => 'Rebar B500B 16 mm, 12 m bars', 'unit' => 't', 'quantity_milli' => 12000]);
        $market = app(MarketService::class);
        $offers = $market->offersFor($item->fresh());
        $this->assertSame(['RB-16', 'RB-16', 'RB-25'], $offers->pluck('product.sku')->all(), 'same unit only, exact size before the cheaper wrong size');
        $this->assertSame($cheap->id, $offers->first()->id);
        $this->assertTrue($offers->first()->exact);
        $this->assertFalse($offers->last()->exact);

        // The cheapest store asks for at least 20 t, so 20 goes into the basket for an item that needs 12.
        Auth::setUser($sup);
        $this->assertSame(1, $market->addCheapestMix($sup, $rfq->fresh()->load('items')));
        $this->assertDatabaseHas('cart_items', ['user_id' => $sup->id, 'offer_id' => $cheap->id, 'quantity_milli' => 20000, 'rfq_item_id' => $item->id]);
        $order = $market->checkout($sup, 'Yard')->first();
        $this->assertSame($rfq->id, $order->rfq_id);
        $this->actingAs($sup)->get(route('market.sourcing', $rfq->number))->assertOk()->assertSee('Rebar B500B 16 mm');
    }

    public function test_a_seller_imports_a_price_list_and_bad_rows_are_reported(): void
    {
        [$a, $seller] = $this->store();
        Category::create(['name' => 'Steel', 'slug' => 'steel']);
        $csv = "sku,name,unit,category,price,moq,stock,lead_days\nRB-16,Rebar 16 mm,t,Steel,698.00,1,120,3\nBAD-1,,t,,5,1,1,1\nWM-1,Mesh,pcs,,63.4,50,2400,2\nRB-16,,,,701.25,2,80,4\n";
        Livewire::actingAs($seller)->test(Offers::class)->call('open', 'import')->set('file', UploadedFile::fake()->createWithContent('list.csv', $csv))->call('import')->assertSee('3 offer(s) saved')->assertSee('Row 3');
        $this->assertSame(2, Offer::where('store_id', $a->id)->count());
        $rb = Offer::whereHas('product', fn ($p) => $p->where('sku', 'RB-16'))->first();
        $this->assertSame(70125, $rb->price_cents);
        $this->assertSame(80000, $rb->stock_milli);
        $this->assertSame('Steel', $rb->product->category->name);

        [$otherStore, $other] = $this->store('Other');
        Livewire::actingAs($other)->test(Offers::class)->assertDontSee('Rebar 16 mm');
        $this->refuses(fn () => app(SellerService::class)->toggle($rb, $otherStore), 'another store');
        $this->refuses(fn () => app(SellerService::class)->saveOffer($otherStore, ['sku' => 'RB-16', 'price' => '1'], $rb), 'another store');
        $this->actingAs($this->buyer())->get(route('market.offers'))->assertForbidden();
    }

    public function test_list_pages_export_excel_and_csv(): void
    {
        $buyer = $this->buyer();
        $this->openRfq($buyer, [$this->supplierUser()], ['title' => '=HYPERLINK("http://x","click")']);
        $xlsx = Livewire::actingAs($buyer)->test(RfqIndex::class)->call('export', 'xlsx');
        $xlsx->assertFileDownloaded();
        $csv = Livewire::actingAs($buyer)->test(RfqIndex::class)->call('export', 'csv');
        $csv->assertFileDownloaded();
        $content = base64_decode($csv->effects['download']['content']);
        $this->assertStringContainsString('Reference', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content, 'a formula in a cell is turned into text');
    }

    /** One delivered, invoiced and approved order of 1,125.00 in the Steel category. */
    private function approvedInvoice(): array
    {
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $cat = Category::create(['name' => 'Steel', 'slug' => 'steel']);
        $rfq = $this->openRfq($buyer, [$sup], ['category_id' => $cat->id, 'budget_cents' => 150000]);
        $q = $this->quote($rfq, $sup, ['12.50', '400.00']);
        $other = $this->supplierUser();
        $rfq->invitations()->create(['supplier_id' => $other->supplier_id]);
        $this->quote($rfq, $other, ['15.00', '420.00']); // 1,200.00
        $rfq = $this->closeRfq($rfq);
        Auth::setUser($buyer);
        $po = app(AwardService::class)->award($rfq, $q->fresh(), $buyer);
        $orders = app(PurchaseOrderService::class);
        $orders->advance($po, $buyer);
        Auth::setUser($sup);
        $orders->advance($po->fresh(), $sup);
        $orders->advance($po->fresh(), $sup);
        $po = $po->fresh()->load('items');
        Auth::setUser($buyer);
        app(ReceiptService::class)->record($po, $buyer, [$po->items[0]->id => '10', $po->items[1]->id => '2.5']);
        Auth::setUser($sup);
        $inv = app(InvoiceService::class)->submit($po->fresh()->load('items'), $sup, ['supplier_ref' => 'S-77', 'issued_on' => now()->toDateString()], [$po->items[0]->id => ['quantity' => '10', 'price' => '12.50'], $po->items[1]->id => ['quantity' => '2.5', 'price' => '400']]);
        Auth::setUser($buyer);
        app(InvoiceService::class)->approve($inv, $buyer);

        return [$buyer, $cat, $inv->fresh(), $po];
    }

    public function test_reports_show_spend_budget_use_and_savings(): void
    {
        [$buyer, $cat] = $this->approvedInvoice();
        $reports = app(ReportService::class);
        $year = (int) now()->format('Y');
        $spend = $reports->spend($buyer, $year);
        $this->assertSame(112500, $spend['total']);
        $this->assertSame(112500, $spend['months'][(int) now()->format('n')]);
        $this->assertSame('Steel', $spend['byCategory'][0]['name']);

        $this->refuses(fn () => $reports->setBudget($year, $cat->id, 0), 'above zero');
        $reports->setBudget($year, $cat->id, 100000);
        $b = $reports->budgets($buyer, $year)->first();
        $this->assertSame(112, $b['pct']);
        $this->assertSame(-12500, $b['left']);

        $s = $reports->savings($buyer, $year);
        $this->assertSame(150000 - 112500, $s['vsBudget']);
        $this->assertSame(116250 - 112500, $s['vsAverage']); // average of 1,125.00 and 1,200.00 is 1,162.50
        $this->assertSame('100%', $reports->kpis($buyer, $year)['Invoices matching first time']['value']);

        // Another buyer sees none of it; a supplier cannot open the page.
        $this->assertSame(0, $reports->spend($this->buyer(), $year)['total']);
        Livewire::actingAs($buyer)->test(Reports::class)->assertSee('1,125')->set('tab', 'budgets')->assertSee('Over')->set('tab', 'savings')->assertSee('Against budget')->set('tab', 'kpis')->assertSee('On-time delivery');
        $this->actingAs($this->supplierUser())->get(route('reports.index'))->assertForbidden();
    }

    public function test_accounting_export_writes_balanced_journal_lines_once(): void
    {
        [$buyer, , $inv] = $this->approvedInvoice();
        [$head, $rows] = app(ReportService::class)->accounting($buyer, now()->subDay()->toDateString(), now()->toDateString(), false);
        $this->assertCount(2, $rows);
        $this->assertSame('1125.00', $rows[0][array_search('Debit', $head, true)]);
        $this->assertSame('1125.00', $rows[1][array_search('Credit', $head, true)]);

        $page = Livewire::actingAs($buyer)->test(Accounting::class)->assertSee('S-77')->call('download', 'xlsx');
        $page->assertFileDownloaded();
        $this->assertNotNull($inv->fresh()->exported_at);
        Livewire::actingAs($buyer)->test(Accounting::class)->assertDontSee('S-77')->set('again', true)->assertSee('S-77');
        $this->actingAs($this->supplierUser())->get(route('accounting.index'))->assertForbidden();
    }

    public function test_every_notification_is_also_written_to_the_outbox_and_can_be_switched_off(): void
    {
        Mail::fake();
        $buyer = $this->buyer();
        Notifier::send($buyer, 'Your request RFQ-1 closed.', '/rfqs/RFQ-1', 'RFQ');
        $mail = OutboxEmail::firstOrFail();
        $this->assertSame($buyer->email, $mail->to);
        $this->assertSame('sent', $mail->status);

        FeatureFlag::updateOrCreate(['key' => 'email_notifications'], ['enabled' => false, 'label' => 'Email']);
        Notifier::send($buyer, 'Second notice', '/', 'RFQ');
        $this->assertSame(1, OutboxEmail::count());
        $this->assertSame(2, $buyer->notifications()->count());

        $this->actingAs($this->admin())->get(route('outbox.index'))->assertOk()->assertSee('Your request RFQ-1 closed.');
        $this->actingAs($buyer)->get(route('outbox.index'))->assertForbidden();
    }

    public function test_a_failing_mail_server_never_stops_the_business_action(): void
    {
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('Connection refused'));
        $buyer = $this->buyer();
        Notifier::send($buyer, 'Hello', '/', 'General');
        $this->assertSame('failed', OutboxEmail::first()->status);
        $this->assertStringContainsString('Connection refused', OutboxEmail::first()->error);
        $this->assertSame(1, $buyer->notifications()->count());
    }

    public function test_reminders_are_sent_once(): void
    {
        $buyer = $this->buyer();
        $quoted = $this->supplierUser();
        $silent = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$quoted, $silent]);
        $this->quote($rfq, $quoted, ['10', '100']);
        $rfq->update(['deadline_at' => now()->addHours(5)]);
        $before = $silent->notifications()->count();
        $reminders = app(ReminderService::class);
        $this->assertSame(1, $reminders->run());
        $this->assertSame($before + 1, $silent->notifications()->count());
        $this->assertStringContainsString('Reminder', $silent->notifications()->latest('created_at')->first()->data['text']);
        $this->assertSame(0, $quoted->notifications()->where('data', 'like', '%Reminder%')->count());
        $this->assertSame(0, $reminders->run(), 'nothing is sent twice');
    }

    public function test_the_interface_language_follows_the_user(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer)->get(route('dashboard'))->assertSee('Purchase orders');
        $this->actingAs($buyer)->post(route('prefs.locale'), ['locale' => 'az'])->assertRedirect();
        $this->assertSame('az', $buyer->fresh()->locale);
        $this->actingAs($buyer->fresh())->get(route('dashboard'))->assertSee('Satınalma sifarişləri')->assertSee('lang="az"', false);
        $this->actingAs($buyer)->post(route('prefs.locale'), ['locale' => 'ru']);
        $this->actingAs($buyer->fresh())->get(route('rfqs.index'))->assertSee('Заказы на закупку');
        $this->actingAs($buyer)->post(route('prefs.locale'), ['locale' => 'xx']);
        $this->assertSame('en', $buyer->fresh()->locale);
    }

    public function test_documents_print_as_pdf_only_for_those_who_may_see_them(): void
    {
        [$buyer, , $inv, $po] = $this->approvedInvoice();
        foreach ([route('orders.pdf', $po), route('rfqs.pdf', $po->rfq), route('invoices.pdf', $inv)] as $url) {
            $r = $this->actingAs($buyer)->get($url);
            $r->assertOk();
            $this->assertStringStartsWith('%PDF', $r->getContent());
            $this->actingAs($this->buyer())->get($url)->assertNotFound();
        }
        $this->actingAs($this->supplierUser())->get(route('orders.pdf', $po))->assertNotFound();
    }

    public function test_every_part_three_page_opens_for_the_right_roles(): void
    {
        [$a, $seller, $worker] = $this->store();
        $this->offer($a, 'A-1', 'Thing', 'pcs', '10', '20');
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $admin = $this->admin();
        foreach (['market.shop', 'market.basket', 'market.sourcing', 'market.orders.index', 'reports.index', 'accounting.index'] as $r) {
            $this->actingAs($buyer)->get(route($r))->assertOk();
        }
        foreach (['market.shop', 'market.basket', 'market.sourcing', 'market.orders.index'] as $r) {
            $this->actingAs($sup)->get(route($r))->assertOk();
        }
        foreach (['market.shop', 'market.orders.index', 'market.offers', 'market.tasks', 'dashboard'] as $r) {
            $this->actingAs($seller)->get(route($r))->assertOk();
        }
        foreach (['market.orders.index', 'market.tasks', 'dashboard'] as $r) {
            $this->actingAs($worker)->get(route($r))->assertOk();
        }
        foreach (['markets.index', 'outbox.index', 'reports.index', 'accounting.index', 'market.shop'] as $r) {
            $this->actingAs($admin)->get(route($r))->assertOk();
        }
        foreach (['market.basket', 'market.sourcing', 'reports.index', 'markets.index', 'rfqs.index'] as $r) {
            $this->actingAs($seller)->get(route($r))->assertForbidden();
        }
        $this->actingAs($worker)->get(route('market.offers'))->assertForbidden();
        $this->actingAs($sup)->get(route('market.tasks'))->assertForbidden();
        Livewire::actingAs($buyer)->test(Basket::class)->call('checkout')->assertDispatched('toast');
    }
}
