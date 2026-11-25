<?php

namespace Database\Seeders;

use Database\Seeders\Support\Fixture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketplaceInventorySeeder extends Seeder
{
    private const DISTRICTS = ['Binəqədi','Nərimanov','Nəsimi','Yasamal','Səbail','Xətai','Sabunçu','Suraxanı','Xəzər','Nizami',
        'Xırdalan','Sumqayıt','Gəncə','Şəki','Quba','Lənkəran','Şirvan','Mingəçevir','Bərdə','Naxçıvan','Ağdaş','Masallı','Qəbələ','Şamaxı'];
    private const SHOP = ['Təchizat Mərkəzi','Texniki Mallar','Sənaye Bazarı','Tikinti Marketi','Ticarət Evi',
        'Peşəkar Avadanlıqlar','Topdansatış Mərkəzi','İstehsalat Təchizatı'];

    public function run(): void
    {
        $now = Fixture::now();
        $groups = DB::table('categories')->whereNull('parent_id')->whereIn('name', array_keys(ReferenceCatalogSeeder::GROUPS))->orderBy('id')->get(['id','name']);
        $products = DB::table('products')->join('categories','products.category_id','=','categories.id')
            ->get(['products.id','products.name','products.sku','products.unit','categories.parent_id']);
        $byGroup = $products->groupBy('parent_id');
        $sellerCount = 105;
        $offerCount = 0;
        for ($i = 0; $i < $sellerCount; $i++) {
            $group = $groups[$i % $groups->count()];
            $city = BusinessIdentitySeeder::city($i);
            $marketName = self::DISTRICTS[$i % count(self::DISTRICTS)].' '.self::SHOP[intdiv($i, count(self::DISTRICTS)) % count(self::SHOP)].' — '.$city;
            $marketId = Fixture::insert('markets', Fixture::stamped([
                'name' => $marketName, 'city' => $city, 'address' => self::DISTRICTS[$i % count(self::DISTRICTS)].' ticarət zonası', 'status' => 'active',
            ], $now), true);
            $storeId = Fixture::insert('stores', Fixture::stamped([
                'name' => $marketName.' / '.$group->name, 'market_id' => $marketId,
                'stall' => 'A-'.str_pad((string) ($i+1), 3,'0',STR_PAD_LEFT), 'status' => 'active',
            ], $now), true);
            Fixture::insert('users', Fixture::stamped([
                'name' => BusinessIdentitySeeder::person($i+245), 'email' => "workflow.seller.$i@example.test",
                'password' => Fixture::passwordHash(), 'role' => 'seller', 'store_id' => $storeId,
                'status' => 'active', 'email_verified_at' => $now,
            ], $now), true);
            if ($i % 2 === 0) {
                Fixture::insert('users', Fixture::stamped([
                    'name' => BusinessIdentitySeeder::person($i+350), 'email' => "workflow.market-worker.$i@example.test",
                    'password' => Fixture::passwordHash(), 'role' => 'market_worker', 'store_id' => $storeId,
                    'status' => 'active', 'email_verified_at' => $now,
                ], $now), true);
            }

            $set = $byGroup->get($group->id)->values();
            $count = 5 + ($i % 6);
            for ($j = 0; $j < $count; $j++) {
                $product = $set[($i*3+$j) % $set->count()];
                $price = 3500 + (crc32($product->sku) % 90000);
                Fixture::insert('offers', Fixture::stamped([
                    'product_id' => $product->id, 'store_id' => $storeId,
                    'price_cents' => $price, 'currency' => 'AZN', 'moq_milli' => 1000,
                    'stock_milli' => (30+($i*7+$j*9)%460)*1000,
                    'lead_days' => 1+($i+$j)%14, 'status' => 'active',
                ], $now));
                $offerCount++;
            }
        }
        Fixture::note('Marketplace', "{$sellerCount} markets, {$sellerCount} independent seller stores, {$offerCount} product offers (5–10 per store).");
    }
}
