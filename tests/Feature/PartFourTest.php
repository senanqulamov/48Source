<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Assistant;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactor;
use App\Livewire\Rfqs\Show as RfqShow;
use App\Livewire\System;
use App\Livewire\Workers;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Currency;
use App\Models\FeatureFlag;
use App\Models\Market;
use App\Models\OutboxEmail;
use App\Models\Rfq;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AssistantService;
use App\Services\AttachmentService;
use App\Services\AwardService;
use App\Services\ContractService;
use App\Services\PurchaseOrderService;
use App\Services\RfqToolsService;
use App\Services\SystemService;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PartFourTest extends TestCase
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

    public function test_request_files_reach_invited_suppliers_only(): void
    {
        Storage::fake('local');
        $buyer = $this->buyer();
        $invited = $this->supplierUser();
        $outsider = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$invited]);
        Livewire::actingAs($buyer)->test(RfqShow::class, ['number' => $rfq->number])->set('upload', UploadedFile::fake()->create('spec.pdf', 120, 'application/pdf'))->assertHasNoErrors();
        $file = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->actingAs($invited)->get(route('files.show', $file->id))->assertOk();
        $this->actingAs($outsider)->get(route('files.show', $file->id))->assertNotFound();
        $this->actingAs($this->buyer())->get(route('files.show', $file->id))->assertNotFound();
        $this->actingAs($invited)->get(route('quotes.form', $rfq))->assertSee('spec.pdf');
        $this->refuses(fn () => app(AttachmentService::class)->addToRfq($rfq, $this->buyer(), UploadedFile::fake()->create('x.pdf', 1)), 'Only the buyer');
        Livewire::actingAs($buyer)->test(RfqShow::class, ['number' => $rfq->number])->set('upload', UploadedFile::fake()->create('run.exe', 10))->assertHasErrors('upload');
        $this->assertSame(1, Attachment::count());
    }

    public function test_quotation_files_are_hidden_from_competitors_and_while_bids_are_sealed(): void
    {
        Storage::fake('local');
        $buyer = $this->buyer();
        $a = $this->supplierUser();
        $b = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$a, $b], ['sealed' => true]);
        $quote = $this->quote($rfq, $a, ['10', '100']);
        Auth::setUser($a);
        $file = app(AttachmentService::class)->addToQuote($quote, $a, UploadedFile::fake()->create('datasheet.pdf', 50));
        $this->refuses(fn () => app(AttachmentService::class)->addToQuote($quote, $b, UploadedFile::fake()->create('x.pdf', 1)), 'owns this quotation');
        $this->actingAs($a)->get(route('files.show', $file->id))->assertOk();
        $this->actingAs($b)->get(route('files.show', $file->id))->assertNotFound();
        $this->actingAs($buyer)->get(route('files.show', $file->id))->assertNotFound(); // sealed until closing
        $this->closeRfq($rfq);
        $this->actingAs($buyer)->get(route('files.show', $file->id))->assertOk();
        $this->refuses(fn () => app(AttachmentService::class)->remove($file, $a), 'no longer');
    }

    public function test_reminding_suppliers_skips_those_who_quoted_and_is_rate_limited(): void
    {
        $buyer = $this->buyer();
        $quoted = $this->supplierUser();
        $silent = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$quoted, $silent]);
        $this->quote($rfq, $quoted, ['10', '100']);
        $before = [$quoted->notifications()->count(), $silent->notifications()->count()];
        Auth::setUser($buyer);
        $tools = app(RfqToolsService::class);
        $this->assertSame(1, $tools->remind($rfq, $buyer));
        $this->assertSame($before[0], $quoted->notifications()->count());
        $this->assertSame($before[1] + 1, $silent->notifications()->count());
        $this->refuses(fn () => $tools->remind($rfq->fresh(), $buyer), '12 hours');
    }

    public function test_duplicating_makes_an_independent_draft(): void
    {
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$sup], ['title' => 'Steel for the deck']);
        Auth::setUser($buyer);
        $copy = app(RfqToolsService::class)->duplicate($rfq, $buyer);
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($rfq->number, $copy->number);
        $this->assertSame('Steel for the deck (copy)', $copy->title);
        $this->assertSame($rfq->items->pluck('name')->all(), $copy->items()->pluck('name')->all());
        $this->assertSame([$sup->supplier_id], $copy->invitations()->pluck('supplier_id')->all());
        $this->assertNull($copy->deadline_at);
        $this->assertSame(0, $copy->quotes()->count());
    }

    public function test_only_an_administrator_reopens_reassigns_or_adjusts(): void
    {
        $buyer = $this->buyer();
        $admin = $this->admin();
        $sup = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$sup]);
        $quote = $this->quote($rfq, $sup, ['10.00', '100.00']); // 350.00
        $rfq = $this->closeRfq($rfq);
        $tools = app(RfqToolsService::class);
        Auth::setUser($buyer);
        $this->refuses(fn () => $tools->adjustQuote($quote, $buyer, '300', 'Discount agreed by phone'), 'administrator');
        $this->refuses(fn () => $tools->reopen($rfq, $buyer, now()->addDay(), 'Closed too early by mistake'), 'administrator');

        Auth::setUser($admin);
        $this->refuses(fn () => $tools->adjustQuote($quote, $admin, '300', 'short'), '10 characters');
        $this->refuses(fn () => $tools->adjustQuote($quote, $admin, '100', 'Far too low to be believable'), 'half');
        $q = $tools->adjustQuote($quote, $admin, '333.33', 'Discount confirmed by email on Monday');
        $this->assertSame(33333, $q->total_cents);
        $this->assertSame(35000, $q->original_total_cents);
        $this->assertSame(33333, (int) $q->items()->sum('total_cents'));
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $sup->id]);
        // A second adjustment keeps the first original.
        $this->assertSame(35000, $tools->adjustQuote($q, $admin, '330', 'Further discount agreed in the meeting')->original_total_cents);

        $other = $this->buyer();
        $tools->reassign($rfq, $admin, $other->id);
        $this->assertSame($other->id, $rfq->fresh()->buyer_id);
        $this->actingAs($buyer)->get(route('rfqs.show', $rfq))->assertNotFound();

        $tools->reopen($rfq->fresh(), $admin, now()->addDays(2)->toDateTimeString(), 'Closed too early by mistake');
        $this->assertTrue($rfq->fresh()->isOpenForQuotes());
    }

    public function test_award_scenarios_compare_single_supplier_and_item_split(): void
    {
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $b = $this->supplierUser(['name' => 'Beta Metals']);
        $rfq = $this->openRfq($buyer, [$a, $b]);
        $this->quote($rfq, $a, ['10.00', '120.00']); // 100 + 300 = 400
        $this->quote($rfq, $b, ['14.00', '100.00']); // 140 + 250 = 390
        $this->actingAs($buyer)->get(route('rfqs.scenarios', $this->closeRfq($rfq)))->assertOk()
            ->assertSee('Lowest single supplier')->assertSee('$390.00')->assertSee('Cheapest supplier for each item')->assertSee('$350.00')->assertSee('Lowest total');
        $this->actingAs($a)->get(route('rfqs.scenarios', $rfq))->assertForbidden();
    }

    public function test_the_assistant_reads_a_sentence_and_only_saves_when_asked(): void
    {
        Category::create(['name' => 'Steel & metals', 'slug' => 'steel']);
        $d = app(AssistantService::class)->draftFromSentence('We need 40 t rebar 16 mm, 200 sheets welded mesh and 15 tie wire coils for the bridge deck, needed in 4 weeks');
        $this->assertSame([['Rebar 16 mm', '40', 't'], ['Welded mesh', '200', 'sheets'], ['Tie wire coils', '15', 'pcs']], array_map(fn ($i) => [$i['name'], $i['quantity'], $i['unit']], $d['items']));
        $this->assertSame(14, $d['deadline_days']);
        $this->assertSame(28, $d['needed_days']);
        $this->assertStringContainsString('Bridge deck', $d['title']);
        $this->assertSame([], app(AssistantService::class)->draftFromSentence('please help me buy things')['items']);

        $buyer = $this->buyer();
        $page = Livewire::actingAs($buyer)->test(Assistant::class)->set('sentence', '12.5 t cement and 30 pcs safety signs')->call('propose')->assertSee('Cement')->assertSee('12.5');
        $this->assertSame(0, Rfq::count(), 'a proposal saves nothing');
        $page->call('openWizard')->assertRedirect(route('rfqs.create'));
        $this->assertSame(0, Rfq::count(), 'opening the wizard still saves nothing');
        $this->actingAs($this->supplierUser())->get(route('assistant.index'))->assertForbidden();
    }

    public function test_the_assistant_reviews_quotations_in_plain_sentences(): void
    {
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $b = $this->supplierUser(['name' => 'Beta Metals']);
        $rfq = $this->openRfq($buyer, [$a, $b], ['budget_cents' => 30000]);
        $this->quote($rfq, $a, ['10.00', '100.00']);
        $this->quote($rfq, $b, ['20.00', null]);
        $rfq = $this->closeRfq($rfq);
        $text = collect(app(AssistantService::class)->reviewQuotations($rfq, app(\App\Services\QuoteEvaluator::class), app(AwardService::class)))->pluck('text')->implode(' | ');
        $this->assertStringContainsString('Beta Metals priced 1 of 2 items', $text);
        $this->assertStringContainsString('$50.00 over the budget', $text);
        Livewire::actingAs($buyer)->test(Assistant::class)->set('review', $rfq->number)->call('summarise')->assertSee('Beta Metals priced 1 of 2 items');
        // Another buyer cannot read it through the assistant.
        Livewire::actingAs($this->buyer())->test(Assistant::class)->set('review', $rfq->number)->assertDontSee('Beta Metals');
    }

    public function test_the_assistant_flags_outlier_prices_and_does_not_compare_mixed_currencies(): void
    {
        $buyer = $this->buyer();
        $a = $this->supplierUser(['name' => 'Alpha Steel']);
        $b = $this->supplierUser(['name' => 'Beta Metals']);
        $c = $this->supplierUser(['name' => 'Gamma Works']);
        $d = $this->supplierUser(['name' => 'Delta Industrial']);
        $rfq = $this->openRfq($buyer, [$a, $b, $c, $d]);
        $this->quote($rfq, $a, ['10.00', '100.00']);
        $this->quote($rfq, $b, ['11.00', '100.00']);
        $this->quote($rfq, $c, ['30.00', '100.00']);
        $this->quote($rfq, $d, ['12.00', '100.00'], ['currency' => 'EUR']);
        $rfq = $this->closeRfq($rfq);

        $flags = app(AssistantService::class)->unusualPrices($rfq);
        $this->assertSame('Gamma Works', $flags[0]['supplier']);
        $text = collect(app(AssistantService::class)->reviewQuotations($rfq, app(\App\Services\QuoteEvaluator::class), app(AwardService::class)))->pluck('text')->implode(' | ');
        $this->assertStringContainsString('different currencies (USD, EUR)', $text);
        $this->actingAs($buyer)->get(route('rfqs.show', $rfq))->assertSee('Summarise');
        Livewire::actingAs($buyer)->test(Assistant::class)->set('review', $rfq->number)->call('unusual')->assertSee('Gamma Works')->assertSee('Unusual prices');
    }

    public function test_sign_in_with_an_email_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'amy@example.test', 'password' => Hash::make('correct-Horse-9'), 'two_factor' => true])->fresh();
        Livewire::test(Login::class)->set('email', 'amy@example.test')->set('password', 'correct-Horse-9')->call('login')->assertRedirect(route('two-factor'));
        $this->assertGuest();
        $this->assertNotNull(Cache::get("2fa:{$user->id}"));

        Cache::put("2fa:{$user->id}", Hash::make('123456'), 600);
        Livewire::test(TwoFactor::class)->set('code', '000000')->call('verify')->assertHasErrors('code');
        $this->assertGuest();
        Livewire::test(TwoFactor::class)->set('code', '123 456')->call('verify')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull(Cache::get("2fa:{$user->id}"), 'a code works once');
    }

    public function test_a_wrong_password_never_sends_a_code_and_the_code_page_needs_a_pending_sign_in(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'amy@example.test', 'password' => Hash::make('correct-Horse-9'), 'two_factor' => true])->fresh();
        Livewire::test(Login::class)->set('email', 'amy@example.test')->set('password', 'wrong-password')->call('login')->assertHasErrors('email');
        $this->assertNull(Cache::get("2fa:{$user->id}"));
        $this->get(route('two-factor'))->assertRedirect(route('login'));
    }

    public function test_email_copies_respect_the_users_choice(): void
    {
        Mail::fake();
        $on = $this->buyer();
        $off = $this->buyer();
        $off->update(['email_notices' => false]);
        Notifier::send([$on, $off->fresh()], 'Something happened', '/', 'General');
        $this->assertSame([$on->email], OutboxEmail::pluck('to')->all());
        $this->assertSame(1, $off->notifications()->count());
    }

    public function test_currencies_are_managed_by_the_administrator(): void
    {
        $admin = $this->admin();
        $this->assertSame(['AZN', 'EUR', 'USD'], Currency::codes());
        Livewire::actingAs($admin)->test(System::class)->set('tab', 'currencies')->set('cur.code', 'gbp')->set('cur.name', 'Pound sterling')->call('addCurrency')->call('toggleCurrency', 'AZN');
        $this->assertSame(['EUR', 'GBP', 'USD'], Currency::codes());
        Livewire::actingAs($admin)->test(System::class)->set('cur.code', 'EURO')->set('cur.name', 'x')->call('addCurrency');
        $this->assertSame(4, Currency::count());
        // A deactivated currency is refused on a new quotation.
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$sup]);
        try {
            $this->quote($rfq, $sup, ['10', '100'], ['currency' => 'AZN']);
            $this->fail('A deactivated currency was accepted.');
        } catch (BusinessRule $e) {
            $this->assertStringContainsString('currency', $e->getMessage());
        }
        $this->actingAs($buyer)->get(route('system.index'))->assertForbidden();
    }

    public function test_feature_flags_and_health_on_the_system_page(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(System::class)->assertSee('Database')->assertSee('File storage')->set('tab', 'flags')->assertSee('Split awards')->call('toggleFlag', 'split_awards');
        $this->assertFalse(FeatureFlag::isOn('split_awards'));
        $names = array_column(app(SystemService::class)->health(), 'ok', 'name');
        $this->assertTrue($names['Database']);
        $this->assertTrue($names['Cache']);
        $this->assertFalse($names['Scheduler'], 'the scheduler has not run in a test');
    }

    public function test_supplier_import_adds_pending_suppliers_and_reports_bad_rows(): void
    {
        Supplier::factory()->create(['name' => 'Existing Ltd']);
        $csv = "name,email,city,contact,phone,category\nNew One Ltd,sales@new.test,Baku,Ann,,\nExisting Ltd,a@b.test,,,,\n,missing@name.test,,,,\nBad Mail Ltd,not-an-email,,,,\n";
        Livewire::actingAs($this->admin())->test(System::class)->set('tab', 'data')->set('file', UploadedFile::fake()->createWithContent('s.csv', $csv))->call('importSuppliers')->assertSee('1 supplier(s) added')->assertSee('Row 3')->assertSee('Row 5');
        $this->assertSame('pending', Supplier::where('name', 'New One Ltd')->firstOrFail()->status);
        $this->assertSame(2, Supplier::count());
    }

    public function test_a_seller_and_a_supplier_manage_only_their_own_workers(): void
    {
        Mail::fake();
        $store = Store::create(['name' => 'Alpha Store', 'market_id' => Market::create(['name' => 'Central'])->id]);
        $seller = User::factory()->create(['role' => 'seller', 'store_id' => $store->id])->fresh();
        $foreign = User::factory()->create(['role' => 'market_worker', 'store_id' => Store::create(['name' => 'Other', 'market_id' => $store->market_id])->id])->fresh();
        Livewire::actingAs($seller)->test(Workers::class)->assertDontSee($foreign->email)->set('w.name', 'Ilyas Demir')->set('w.email', 'ilyas@example.test')->call('add');
        $worker = User::where('email', 'ilyas@example.test')->firstOrFail();
        $this->assertSame(['market_worker', $store->id, 'invited'], [$worker->role, $worker->store_id, $worker->status]);
        $worker->update(['status' => 'active']);
        Livewire::actingAs($seller)->test(Workers::class)->call('toggle', $worker->id);
        $this->assertSame('disabled', $worker->fresh()->status);
        try {
            Livewire::actingAs($seller)->test(Workers::class)->call('toggle', $foreign->id);
            $this->fail('A seller changed a worker of another store.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
        }
        $this->assertSame('active', $foreign->fresh()->status);

        $sup = $this->supplierUser();
        Livewire::actingAs($sup)->test(Workers::class)->assertSee('Field workers')->set('w.name', 'Jo Field')->set('w.email', 'jo@example.test')->call('add');
        $this->assertSame(['supplier_worker', $sup->supplier_id], [User::where('email', 'jo@example.test')->value('role'), User::where('email', 'jo@example.test')->value('supplier_id')]);
        $this->actingAs($this->buyer())->get(route('workers.index'))->assertForbidden();
    }

    public function test_a_delivered_order_can_start_a_contract_and_a_message(): void
    {
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$sup]);
        $q = $this->quote($rfq, $sup, ['12.50', '400.00']);
        $rfq = $this->closeRfq($rfq);
        Auth::setUser($buyer);
        $po = app(AwardService::class)->award($rfq, $q->fresh(), $buyer);
        $contracts = app(ContractService::class);
        $this->refuses(fn () => $contracts->fromOrder($po->load('items', 'supplier', 'rfq'), $buyer), 'delivered');
        $po->update(['status' => 'delivered', 'delivered_at' => now()]);
        $c = $contracts->fromOrder($po->fresh()->load('items', 'supplier', 'rfq'), $buyer);
        $this->assertSame(['draft', $sup->supplier_id, $po->id], [$c->status, $c->supplier_id, $c->purchase_order_id]);
        $this->assertSame([1250, 40000], $c->items()->pluck('unit_price_cents')->all());

        Livewire::actingAs($sup)->test(\App\Livewire\Orders\Show::class, ['number' => $po->number])->set('msg', 'The truck is delayed by one day.')->call('sendMessage')->assertRedirect();
        $this->assertDatabaseHas('conversations', ['supplier_id' => $sup->supplier_id, 'subject' => "About {$po->number}", 'subject_ref_type' => 'purchase_order']);
    }

    public function test_search_finds_records_of_every_part_and_respects_visibility(): void
    {
        $buyer = $this->buyer();
        app(\App\Services\RequisitionService::class)->save($buyer, ['title' => 'Zebra crossing paint'], [['name' => 'Paint', 'quantity' => '5', 'unit' => 'l']]);
        $this->actingAs($buyer)->get(route('search', ['q' => 'zebra']))->assertOk()->assertSee('Zebra crossing paint')->assertSee('Requisitions');
        $this->actingAs($this->buyer())->get(route('search', ['q' => 'zebra']))->assertDontSee('Zebra crossing paint');
    }

    public function test_new_pages_open_for_the_right_roles(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        $sup = $this->supplierUser();
        foreach (['monitoring.index', 'health.index', 'assistant.index', 'privacy', 'settings'] as $r) {
            $this->actingAs($admin)->get(route($r))->assertOk();
        }
        foreach (['monitoring.index', 'assistant.index', 'privacy'] as $r) {
            $this->actingAs($buyer)->get(route($r))->assertOk();
        }
        $this->actingAs($sup)->get(route('privacy'))->assertOk();
        $this->actingAs($sup)->get(route('workers.index'))->assertOk();
        $this->actingAs($sup)->get(route('monitoring.index'))->assertForbidden();
        $this->actingAs($sup)->get(route('system.index'))->assertForbidden();
        Auth::logout();
        $this->get(route('landing'))->assertOk()->assertSee('Sign in');
        $this->actingAs($sup)->get(route('suppliers.show', $sup->supplier_id))->assertSee('Qualification');
        $this->actingAs($buyer)->get(route('rfqs.index'))->assertSee('Status guide');
    }
}
