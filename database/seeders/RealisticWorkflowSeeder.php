<?php

namespace Database\Seeders;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Scenario-based, internally consistent SYNTHETIC procurement history.
 * Non-production only; never substitutes for imported business transactions.
 * Re-running is idempotent. Its rows are isolated by dedicated references.
 */
class RealisticWorkflowSeeder extends Seeder
{
    private const BATCH = 'workflow-fixtures-v1';

    /** Bounded fictitious supplier profiles (not assertions about real companies). */
    private const SUPPLIERS = [
        ['name' => 'Qaya İnşaat Təchizatı MMC', 'city' => 'Bakı', 'contact' => 'Aysel Məmmədova'],
        ['name' => 'Xəzər Sənaye Komponentləri MMC', 'city' => 'Sumqayıt', 'contact' => 'Rauf Cəfərov'],
        ['name' => 'Zirvə Elektrik Sistemləri MMC', 'city' => 'Bakı', 'contact' => 'Nigar Orucova'],
        ['name' => 'Kür Boru və Fitinq MMC', 'city' => 'Gəncə', 'contact' => 'Cavid Həsənov'],
        ['name' => 'Atlas İT Avadanlıqları MMC', 'city' => 'Bakı', 'contact' => 'Günel Əliyeva'],
        ['name' => 'Sahil Təhlükəsizlik Təchizatı MMC', 'city' => 'Şirvan', 'contact' => 'Ramil Quliyev'],
        ['name' => 'Muğan Logistika Xidmətləri MMC', 'city' => 'Lənkəran', 'contact' => 'Sevinc Babayeva'],
        ['name' => 'Buta Texniki Servis MMC', 'city' => 'Mingəçevir', 'contact' => 'Orxan Abbasov'],
    ];

    /** Price = integer minor units; qty = integer thousandths. */
    private const PACKAGES = [
        ['title' => 'Armatur və bağlama materialları', 'category' => 'Construction materials', 'items' => [
            ['Armatur B500B Ø16', 'ton', 3500, 127000], ['Bağlama məftili', 'kg', 180000, 225], ['Metal distansator', 'pcs', 70000, 140],
        ]],
        ['title' => 'Elektrik paylama komponentləri', 'category' => 'Construction materials', 'items' => [
            ['NYM kabel 3×2.5 mm²', 'm', 450000, 185], ['Avtomat açar 32A', 'pcs', 24000, 2190], ['Paylayıcı lövhə 24 modul', 'pcs', 6000, 18900],
        ]],
        ['title' => 'Nasos stansiyası ehtiyat hissələri', 'category' => 'Industrial equipment', 'items' => [
            ['Flanşlı klapan DN80', 'pcs', 8000, 16800], ['Mexaniki salnik', 'pcs', 16000, 8750], ['Təzyiq sensoru 0–16 bar', 'pcs', 12000, 22400],
        ]],
        ['title' => 'Ofis şəbəkə avadanlığı', 'category' => 'IT hardware', 'items' => [
            ['24-port idarə olunan switch', 'pcs', 8000, 68600], ['Cat6 UTP kabel 305m', 'box', 12000, 21700], ['Wi-Fi access point', 'pcs', 16000, 39100],
        ]],
        ['title' => 'Yanğın təhlükəsizlik avadanlıqları', 'category' => 'Facility maintenance', 'items' => [
            ['Yanğın detektoru', 'pcs', 42000, 3800], ['Yanğın dolabı', 'pcs', 12000, 19600], ['Tüstü xəbərdarlıq sirenası', 'pcs', 16000, 4550],
        ]],
        ['title' => 'Tikinti sahəsi fərdi mühafizə dəsti', 'category' => 'Construction materials', 'items' => [
            ['Qoruyucu dəbilqə', 'pcs', 130000, 1850], ['Əks etdirici jilet', 'pcs', 130000, 970], ['Qoruyucu eynək', 'pcs', 130000, 690],
        ]],
    ];

    public function run(): void
    {
        if (app()->environment(['production', 'staging'])) {
            throw new RuntimeException('Synthetic workflow fixtures cannot be seeded in production.');
        }

        if (! Schema::hasTable('workflow_fixture_records')) {
            throw new RuntimeException('Run php artisan migrate before loading workflow fixtures.');
        }

        if (DB::table('workflow_fixture_records')->where('batch', self::BATCH)->exists()) {
            $this->command?->warn('Workflow fixture batch already exists; no records changed.');
            return;
        }

        // Avoid mixed charts and overwriting genuine procurement or old demo datasets.
        // Reference categories, feature flags and an existing admin account are permitted.
        foreach (['rfqs', 'quotes', 'purchase_orders', 'suppliers', 'invoices', 'market_orders', 'requisitions', 'contracts'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("{$table} already contains business records. Use a new disposable local database; no records were changed.");
            }
        }

        DB::transaction(function (): void {
            $this->call(DatabaseSeeder::class);
            $now = CarbonImmutable::now()->subDay()->startOfDay();
            $buyerIds = $this->buyers($now);
            $supplierRecords = $this->suppliers($buyerIds[0], $now);
            $categoryIds = DB::table('categories')->pluck('id', 'name');
            
            // 72 cases, spread over the preceding 12 months. No future history.
            for ($index = 0; $index < 72; $index++) {
                $ageDays = 355 - ($index * 5);
                $opened = $now->subDays($ageDays)->setTime(9 + ($index % 7), 30);
                $package = self::PACKAGES[$index % count(self::PACKAGES)];
                $buyerId = $buyerIds[$index % count($buyerIds)];
                $status = $this->status($ageDays, $index);
                $reference = sprintf('RFQ-%d-W%04d', $opened->year, $index + 1);
                $categoryId = $categoryIds[$package['category']] ?? null;
                $budget = array_sum(array_map(fn (array $item): int => Money::lineTotal($item[3], $item[2]), $package['items']));
                $deadline = $opened->addDays(16);
                $closed = in_array($status, ['closed', 'awarded'], true) ? $deadline->addHours(1) : null;
                $awarded = $status === 'awarded' ? $deadline->addDays(2) : null;

                $rfqLocation = ['Bakı — əsas anbar', 'Sumqayıt — istehsalat sahəsi', 'Gəncə — regional anbar'][$index % 3];
                $rfqId = $this->insert('rfqs', [
                    'number' => $reference,
                    'title' => $package['title'].' — '.['Şimal sahəsi', 'Mərkəzi anbar', 'Texniki xidmət', 'Yeni inzibati bina'][$index % 4],
                    'description' => 'Təchizat siyahısına uyğun sertifikatlı məhsulların satınalınması.',
                    'type' => $index % 5 === 0 ? 'public' : 'invited',
                    'category_id' => $categoryId,
                    'buyer_id' => $buyerId,
                    'status' => $status,
                    'currency' => 'AZN',
                    'budget_cents' => $budget,
                    'deadline_at' => $deadline->toDateTimeString(),
                    'delivery_location' => $rfqLocation,
                    'required_by' => $deadline->addDays(32)->toDateString(),
                    'requirements' => 'İstehsalçı sənədləri, zəmanət şərtləri və uyğunluq sertifikatları tələb olunur.',
                    'payment_terms' => 'Net 30',
                    'scoring_preset' => 'balanced',
                    'sealed' => false,
                    'round' => 1,
                    'published_at' => $status !== 'draft' ? $opened->addDay()->toDateTimeString() : null,
                    'closed_at' => $closed?->toDateTimeString(),
                    'awarded_at' => $awarded?->toDateTimeString(),
                    'cancelled_at' => $status === 'cancelled' ? $opened->addDays(4)->toDateTimeString() : null,
                    'cancel_reason' => $status === 'cancelled' ? 'Layihənin miqyası yenidən təsdiqlənməlidir.' : null,
                    'created_at' => $opened->toDateTimeString(),
                    'updated_at' => ($awarded ?? $closed ?? $opened)->toDateTimeString(),
                ]);

                $rfqItems = [];
                foreach ($package['items'] as $position => [$name, $unit, $quantityMilli, $priceCents]) {
                    $rfqItems[] = [
                        'id' => $this->insert('rfq_items', [
                            'rfq_id' => $rfqId, 'position' => $position + 1,
                            'name' => $name, 'description' => 'Texniki spesifikasiyaya uyğun təchizat.',
                            'quantity_milli' => $quantityMilli, 'unit' => $unit,
                            'target_price_cents' => $priceCents,
                            'created_at' => $opened->toDateTimeString(),
                            'updated_at' => $opened->toDateTimeString(),
                        ]),
                        'name' => $name, 'unit' => $unit,
                        'qty' => $quantityMilli, 'target' => $priceCents,
                    ];
                }

                if (in_array($status, ['draft', 'cancelled'], true)) {
                    continue;
                }

                // Suppliers compete on the SAME material list, with coherent price differences.
                // Keep all suppliers in the same AZN currency: no invented FX conversion.
                $candidates = [];
                for ($rank = 0; $rank < 3; $rank++) {
                    $supplier = $supplierRecords[($index * 3 + $rank) % count($supplierRecords)];
                    $responseAt = $opened->addDays(3 + $rank);
                    $this->insert('rfq_invitations', [
                        'rfq_id' => $rfqId,
                        'supplier_id' => $supplier['id'],
                        'status' => 'accepted', 'responded_at' => $responseAt->toDateTimeString(),
                        'created_at' => $opened->toDateTimeString(), 'updated_at' => $responseAt->toDateTimeString(),
                    ]);
                    // Open requests retain 1–2 outstanding supplier replies.
                    if ($status === 'open' && $rank > ($index % 2)) {
                        continue;
                    }
                    $discountBasisPoints = [10000, 9580, 10450][$rank] + (($index % 5) - 2) * 35;
                    $total = 0;
                    $linePrices = [];
                    foreach ($rfqItems as $item) {
                        $unitPrice = intdiv($item['target'] * $discountBasisPoints + 5000, 10000);
                        $lineTotal = Money::lineTotal($unitPrice, $item['qty']);
                        $total += $lineTotal;
                        $linePrices[] = [$item, $unitPrice, $lineTotal];
                    }
                    $quoteStatus = $status === 'awarded' ? ($rank === 1 ? 'won' : 'lost') : 'submitted';
                    $quotedAt = $responseAt->addHours(2);
                    $quoteId = $this->insert('quotes', [
                        'rfq_id' => $rfqId, 'supplier_id' => $supplier['id'],
                        'submitted_by' => $supplier['user_id'], 'status' => $quoteStatus,
                        'currency' => 'AZN', 'delivery_days' => 8 + $rank * 3,
                        'payment_terms' => 'Net 30',
                        'valid_until' => $deadline->addDays(25)->toDateString(),
                        'notes' => 'Qiymətə anbara çatdırılma və standart zəmanət daxildir.',
                        'total_cents' => $total, 'revision' => 0,
                        'submitted_at' => $quotedAt->toDateTimeString(),
                        'created_at' => $quotedAt->toDateTimeString(),
                        'updated_at' => $quotedAt->toDateTimeString(),
                    ]);
                    foreach ($linePrices as [$item, $unitPrice, $lineTotal]) {
                        $this->insert('quote_items', [
                            'quote_id' => $quoteId, 'rfq_item_id' => $item['id'],
                            'unit_price_cents' => $unitPrice, 'total_cents' => $lineTotal,
                            'created_at' => $quotedAt->toDateTimeString(),
                            'updated_at' => $quotedAt->toDateTimeString(),
                        ]);
                    }
                    $candidates[] = ['id' => $quoteId, 'supplier' => $supplier, 'total' => $total, 'lines' => $linePrices];
                }
                if ($status === 'awarded') {
                    $this->award($rfqId, $buyerId, $reference, $awarded, $deadline, $rfqLocation, $candidates[1], $ageDays, $index);
                }
            }
        }, attempts: 1);

        $this->command?->info('Seeded 72 linked synthetic procurement workflows in AZN. No existing business data was overwritten.');
        $this->command?->warn('Synthetic fixtures are not genuine production transactions.');
    }

    private function status(int $ageDays, int $index): string
    {
        if ($ageDays < 5) return 'draft';
        if ($ageDays <= 15) return 'open';
        if ($ageDays <= 25) return 'closed';
        if ($index % 13 === 0) return 'cancelled';
        if ($index % 7 === 0) return 'closed';
        return 'awarded';
    }

    private function buyers(CarbonImmutable $now): array
    {
        $buyers = [];
        foreach ([['Təchizat rəhbəri', 'Procurement'], ['Layihə satınalma mütəxəssisi', 'Construction'], ['Texniki təchizat üzrə mütəxəssis', 'Operations']] as $i => [$title, $department]) {
            $email = "workflow.buyer.$i@example.test";
            if (DB::table('users')->where('email', $email)->exists()) {
                throw new RuntimeException('Workflow fixture account already exists outside the tracked batch: '.$email);
            }
            $buyers[] = $this->insert('users', [
                'name' => $title, 'email' => $email, 'role' => 'buyer',
                // Password is not printed; explicitly configured only for disposable local testing.
                'password' => Hash::make((string) (env('WORKFLOW_SEED_PASSWORD') ?: Str::random(48))),
                'department' => $department, 'status' => 'active',
                'email_verified_at' => $now->toDateTimeString(),
                'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString(),
            ]);
        }
        return $buyers;
    }

    private function suppliers(int $approvedBy, CarbonImmutable $now): array
    {
        $category = DB::table('categories')->where('name', 'Construction materials')->value('id');
        $records = [];
        foreach (self::SUPPLIERS as $i => $profile) {
            $email = "workflow.supplier.$i@example.test";
            if (DB::table('suppliers')->where('email', $email)->exists() || DB::table('users')->where('email', $email)->exists()) {
                throw new RuntimeException('Workflow fixture supplier already exists outside the tracked batch: '.$email);
            }
            $supplierId = $this->insert('suppliers', [
                'name' => $profile['name'], 'legal_name' => $profile['name'],
                'category_id' => $category, 'city' => $profile['city'], 'country' => 'Azərbaycan',
                'contact_name' => $profile['contact'], 'email' => $email,
                'phone' => null, 'status' => 'active', 'rating_tenths' => 40,
                'approved_at' => $now->subMonths(14)->toDateTimeString(), 'approved_by' => $approvedBy,
                'created_at' => $now->subMonths(15)->toDateTimeString(), 'updated_at' => $now->subMonths(14)->toDateTimeString(),
            ]);
            $userId = $this->insert('users', [
                'name' => $profile['contact'], 'email' => $email, 'role' => 'supplier',
                'supplier_id' => $supplierId, 'status' => 'active',
                'password' => Hash::make((string) (env('WORKFLOW_SEED_PASSWORD') ?: Str::random(48))),
                'email_verified_at' => $now->toDateTimeString(),
                'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString(),
            ]);
            $records[] = ['id' => $supplierId, 'user_id' => $userId];
        }
        return $records;
    }

    private function award(int $rfqId, int $buyerId, string $reference, CarbonImmutable $awarded, CarbonImmutable $deadline, string $rfqLocation, array $winning, int $ageDays, int $index): void
    {
        $status = match (true) {
            $ageDays < 40 => 'draft',
            $ageDays < 70 => 'issued',
            $ageDays < 100 => 'acknowledged',
            $ageDays < 135 => 'in_transit',
            $ageDays < 165 => 'delivered',
            default => $index % 7 === 0 ? 'delivered' : 'invoiced',
        };
        $issued = $status !== 'draft' ? $awarded->addDay() : null;
        $ack = in_array($status, ['acknowledged', 'in_transit', 'delivered', 'invoiced'], true) ? $awarded->addDays(3) : null;
        $shipped = in_array($status, ['in_transit', 'delivered', 'invoiced'], true) ? $awarded->addDays(6) : null;
        $delivered = in_array($status, ['delivered', 'invoiced'], true) ? $awarded->addDays(14) : null;
        $invoiced = $status === 'invoiced' ? $awarded->addDays(17) : null;
        $poNumber = str_replace('RFQ-', 'PO-', $reference);
        $orderId = $this->insert('purchase_orders', [
            'number' => $poNumber, 'rfq_id' => $rfqId,
            'supplier_id' => $winning['supplier']['id'], 'quote_id' => $winning['id'],
            'buyer_id' => $buyerId, 'status' => $status, 'currency' => 'AZN',
            'total_cents' => $winning['total'], 'payment_terms' => 'Net 30',
            'delivery_location' => $rfqLocation,
            'expected_at' => $deadline->addDays(30)->toDateString(),
            'award_justification' => 'Texniki uyğunluq, təhvil müddəti və yekun təklif məbləği nəzərə alınmışdır.',
            'issued_at' => $issued?->toDateTimeString(),
            'acknowledged_at' => $ack?->toDateTimeString(),
            'shipped_at' => $shipped?->toDateTimeString(),
            'delivered_at' => $delivered?->toDateTimeString(),
            'invoiced_at' => $invoiced?->toDateTimeString(),
            'created_at' => $awarded->toDateTimeString(),
            'updated_at' => ($invoiced ?? $delivered ?? $shipped ?? $ack ?? $issued ?? $awarded)->toDateTimeString(),
        ]);
        foreach ($winning['lines'] as [$item, $unitPrice, $total]) {
            $this->insert('purchase_order_items', [
                'purchase_order_id' => $orderId, 'rfq_item_id' => $item['id'],
                'name' => $item['name'], 'quantity_milli' => $item['qty'],
                'unit' => $item['unit'], 'unit_price_cents' => $unitPrice,
                'total_cents' => $total,
                'created_at' => $awarded->toDateTimeString(), 'updated_at' => $awarded->toDateTimeString(),
            ]);
        }
        if (! $delivered) return;

        $receiptId = $this->insert('goods_receipts', [
            'number' => str_replace('RFQ-', 'GR-', $reference),
            'purchase_order_id' => $orderId, 'received_by' => $buyerId,
            'received_on' => $delivered->toDateString(), 'note' => 'Tam həcmdə qəbul edildi.',
            'created_at' => $delivered->toDateTimeString(), 'updated_at' => $delivered->toDateTimeString(),
        ]);
        foreach (DB::table('purchase_order_items')->where('purchase_order_id', $orderId)->get() as $line) {
            $this->insert('goods_receipt_items', [
                'goods_receipt_id' => $receiptId, 'purchase_order_item_id' => $line->id,
                'accepted_milli' => $line->quantity_milli, 'rejected_milli' => 0,
                'note' => null, 'created_at' => $delivered->toDateTimeString(),
                'updated_at' => $delivered->toDateTimeString(),
            ]);
        }
        if (! $invoiced) return;

        $invoiceId = $this->insert('invoices', [
            'number' => str_replace('RFQ-', 'INV-', $reference),
            'supplier_ref' => 'WF-INV-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
            'purchase_order_id' => $orderId, 'supplier_id' => $winning['supplier']['id'],
            'submitted_by' => $winning['supplier']['user_id'], 'status' => 'paid',
            'currency' => 'AZN', 'total_cents' => $winning['total'],
            'issued_on' => $invoiced->toDateString(), 'due_on' => $invoiced->addDays(30)->toDateString(),
            'paid_at' => $invoiced->addDays(14)->toDateTimeString(),
            'created_at' => $invoiced->toDateTimeString(),
            'updated_at' => $invoiced->addDays(14)->toDateTimeString(),
        ]);
        foreach (DB::table('purchase_order_items')->where('purchase_order_id', $orderId)->get() as $line) {
            $this->insert('invoice_items', [
                'invoice_id' => $invoiceId, 'purchase_order_item_id' => $line->id,
                'quantity_milli' => $line->quantity_milli, 'unit_price_cents' => $line->unit_price_cents,
                'total_cents' => $line->total_cents,
                'created_at' => $invoiced->toDateTimeString(), 'updated_at' => $invoiced->toDateTimeString(),
            ]);
        }
    }

    private function insert(string $table, array $values): int
    {
        $id = DB::table($table)->insertGetId($values);
        DB::table('workflow_fixture_records')->insert([
            'table_name' => $table, 'record_id' => $id,
            'batch' => self::BATCH, 'created_at' => now(),
        ]);
        return $id;
    }
}