<?php

namespace Database\Seeders;

use Database\Seeders\Support\Fixture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessIdentitySeeder extends Seeder
{
    private const FIRST = ['Aysel','Elvin','Nigar','Rauf','Günel','Cavid','Sevinc','Kamran','Leyla','Emin','Zəhra','Tural','Aynur','Orxan','Səbinə','Ramil','Fidan','Tərlan','Lalə','Vüqar'];
    private const LAST = ['Məmmədova','Əliyev','Həsənova','Quliyev','Abbasova','Cəfərov','Hüseynova','İsmayılov','Nəcəfli','Kərimov','Vəliyeva','Sultanov','Rzayeva','Babayeva','Mirzəyev','Əhmədli'];
    private const PREFIX = ['Abşeron','Xəzər','Şirvan','Araz','Qobustan','Zirvə','Buta','Kür','Muğan','Sahil','Atlas','Qaya','Zəfər','Bakı','Qafqaz','Cənub','Şimal','Ulduz','Bərəkət','Naxçıvan'];
    private const CITIES = ['Bakı','Sumqayıt','Gəncə','Mingəçevir','Şirvan','Lənkəran','Quba','Şəki','Naxçıvan','Xırdalan','Masazır','Bərdə'];

    public static function person(int $index): string
    {
        return self::FIRST[$index % count(self::FIRST)].' '.self::LAST[intdiv($index, count(self::FIRST)) % count(self::LAST)];
    }

    public static function city(int $i): string
    {
        return self::CITIES[$i % count(self::CITIES)];
    }

    public function run(): void
    {
        $now = Fixture::now();
        $groups = DB::table('categories')->whereNull('parent_id')->whereIn('name', array_keys(ReferenceCatalogSeeder::GROUPS))->orderBy('id')->pluck('id')->all();
        $admin = Fixture::insert('users', Fixture::stamped([
            'name' => 'Nərgiz Məmmədova', 'email' => 'admin@workflow.48source.test',
            'password' => Fixture::passwordHash(), 'role' => 'admin',
            'status' => 'active', 'department' => 'Administration',
            'email_verified_at' => $now,
        ], $now), true);

        for ($i = 0; $i < 105; $i++) {
            Fixture::insert('users', Fixture::stamped([
                'name' => self::person($i), 'email' => "workflow.buyer.$i@example.test",
                'password' => Fixture::passwordHash(), 'role' => 'buyer',
                'status' => 'active', 'department' => ['Procurement','Construction','Engineering','Operations','Facilities'][$i % 5],
                'email_verified_at' => $now,
            ], $now), true);
        }

        $trade = ['Tikinti Təchizatı','Elektrik Sistemləri','Texniki Avadanlıqlar','Sənaye Komponentləri',
            'İnformasiya Texnologiyaları','Mexaniki Sistemlər','Təhlükəsizlik Həlləri','Ofis Təchizatı',
            'Daşınma Xidmətləri','Tibbi Avadanlıqlar','Kənd Təsərrüfatı','Ticarət və Xidmət'];
        for ($i = 0; $i < 130; $i++) {
            $name = self::PREFIX[$i % count(self::PREFIX)].' '.$trade[intdiv($i, count(self::PREFIX)) % count($trade)].' MMC';
            $categoryId = $groups[$i % count($groups)];
            $active = $i < 120;
            $supplierId = Fixture::insert('suppliers', Fixture::stamped([
                'name' => $name, 'legal_name' => $name,
                'category_id' => $categoryId, 'city' => self::city($i), 'country' => 'Azərbaycan',
                'contact_name' => self::person($i + 111), 'email' => "workflow.supplier.$i@example.test",
                'status' => $active ? 'active' : 'pending',
                'rating_tenths' => 35 + ($i % 15), 'approved_at' => $active ? $now->subMonths(14) : null,
                'approved_by' => $active ? $admin : null,
            ], $now->subMonths(15)), true);

            Fixture::insert('users', Fixture::stamped([
                'name' => self::person($i + 111), 'email' => "workflow.supplier.$i@example.test",
                'password' => Fixture::passwordHash(), 'role' => 'supplier',
                'supplier_id' => $supplierId, 'status' => 'active',
                'email_verified_at' => $now,
            ], $now), true);
        }

        Fixture::note('Identity', '1 admin, 105 buyers, 130 suppliers + supplier accounts (120 active, 10 pending).');
    }
}
