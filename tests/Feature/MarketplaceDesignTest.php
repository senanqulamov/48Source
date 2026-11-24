<?php

namespace Tests\Feature;

use App\Livewire\Market\Shop;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Market;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Marketplace, Products and Markets pages as in the accepted design. */
class MarketplaceDesignTest extends TestCase
{
    use RefreshDatabase;

    private function stock(): array
    {
        $steel = Category::create(['name' => 'Steel', 'slug' => 'steel']);
        $tools = Category::create(['name' => 'Tools', 'slug' => 'tools']);
        $north = Market::create(['name' => 'North Market', 'city' => 'Baku', 'status' => 'active']);
        $south = Market::create(['name' => 'South Market', 'city' => 'Ganja', 'status' => 'active']);
        $a = Store::create(['market_id' => $north->id, 'name' => 'Alpha Store', 'status' => 'active']);
        $b = Store::create(['market_id' => $south->id, 'name' => 'Beta Store', 'status' => 'active']);
        $rebar = Product::create(['sku' => 'RB-16', 'name' => 'Rebar 16 mm', 'category_id' => $steel->id, 'unit' => 't', 'status' => 'active']);
        $drill = Product::create(['sku' => 'DR-01', 'name' => 'Hammer drill', 'category_id' => $tools->id, 'unit' => 'pcs', 'status' => 'active']);
        $cheap = Offer::create(['product_id' => $rebar->id, 'store_id' => $a->id, 'price_cents' => 70000, 'stock_milli' => 50000, 'lead_days' => 6, 'moq_milli' => 1000]);
        $fast = Offer::create(['product_id' => $rebar->id, 'store_id' => $b->id, 'price_cents' => 77000, 'stock_milli' => 20000, 'lead_days' => 2, 'moq_milli' => 1000]);
        Offer::create(['product_id' => $drill->id, 'store_id' => $b->id, 'price_cents' => 16400, 'stock_milli' => 4000, 'lead_days' => 1, 'moq_milli' => 1000]);

        return compact('steel', 'tools', 'north', 'south', 'a', 'b', 'rebar', 'drill', 'cheap', 'fast');
    }

    public function test_the_shop_has_the_filter_panel_both_views_and_the_compare_drawer(): void
    {
        $d = $this->stock();
        $buyer = $this->buyer();

        Livewire::actingAs($buyer)->test(Shop::class)
            ->assertSee('Filters')->assertSee('All categories')->assertSee('Delivery within')->assertSee('By product')->assertSee('All offers')->assertSee('Best match')
            ->assertSee('Compare 2 offers')->assertSee('compare to save up to 9%')->assertSee('Hammer drill')
            ->call('setCat', (string) $d['steel']->id)->assertSee('Rebar 16 mm')->assertDontSee('Hammer drill')
            ->call('setCat', '')->set('stores', [(string) $d['a']->id])->assertSee('Rebar 16 mm')->assertDontSee('Hammer drill')
            ->set('stores', [])->call('setLead', '2')->assertSee('Compare 1 offer')->assertSee('Hammer drill')
            ->call('setLead', '')->set('markets', [(string) $d['north']->id])->assertDontSee('Hammer drill')
            ->call('clear')->call('setView', 'offers')->assertSee('Min. order')->assertSee('Alpha Store')->assertSee('Beta Store')
            ->call('show', $d['rebar']->id)->assertSee('Compare offers')->assertSee('lowest price · Alpha Store')->assertSee('fastest · Beta Store')->assertSee('+10%')->assertSee('Fastest')
            ->call('add', $d['fast']->id);
        $this->assertSame(1, CartItem::where('user_id', $buyer->id)->where('offer_id', $d['fast']->id)->count());
    }

    public function test_the_products_page_has_the_band_chips_availability_and_table_view(): void
    {
        $d = $this->stock();
        Livewire::actingAs($this->buyer())->test(Shop::class, ['page' => 'products'])
            ->assertSee('Stock value')->assertSee('Out of stock')->assertSee('Showcase')->assertSee('Any availability')->assertSee('Steel')->assertSee('Tools')
            ->set('avail', 'low')->assertSee('Hammer drill')->assertDontSee('Rebar 16 mm')
            ->set('avail', 'in')->assertSee('Rebar 16 mm')->assertDontSee('Hammer drill')
            ->set('avail', '')->call('setView', 'table')->assertSee('Availability')->assertSee('RB-16');
        $this->actingAs($this->admin())->get(route('market.products'))->assertOk()->assertSee('<h1 class="ph-t">Products</h1>', false);
        $this->actingAs($this->supplierUser())->get(route('market.products', ['markets' => [$d['south']->id]]))->assertOk()->assertSee('Hammer drill');
    }

    public function test_markets_are_shown_as_cards_and_paused_ones_only_to_those_who_manage_them(): void
    {
        $this->stock();
        Market::create(['name' => 'Paused Yard', 'city' => 'Sumqayit', 'status' => 'paused']);

        $this->actingAs($this->buyer())->get(route('market.places'))->assertOk()
            ->assertSee('North Market')->assertSee('South Market')->assertSee('Open market')->assertSee('Products listed')->assertSee('class="m-card"', false)->assertDontSee('Paused Yard');
        $this->actingAs($this->admin())->get(route('market.places'))->assertOk()->assertSee('Paused Yard')->assertSee('Manage markets and stores');
    }
}
