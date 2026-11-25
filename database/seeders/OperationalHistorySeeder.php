<?php

namespace Database\Seeders;

use App\Models\Quote;
use App\Models\Rfq;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\Fixture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OperationalHistorySeeder extends Seeder
{
    public function run(): void
    {
        $this->marketplace();
        $this->contracts();
        $this->supplierAssurance();
        $this->sourcingActivity();
        $this->communication();
        $this->budgets();
        $this->sequences();
        Fixture::note('Operations', 'Marketplace orders, contracts, assessments, RFIs, messages, documents, scores, logs and notifications.');
    }

    private function marketplace(): void
    {
        $now = Fixture::now();
        $buyers = DB::table('users')->where('role','buyer')->orderBy('id')->pluck('id')->all();
        $stores = DB::table('stores')->orderBy('id')->pluck('id')->all();
        $workers = DB::table('users')->where('role','market_worker')->pluck('id','store_id');
        $marketOrders = 170;
        for ($i = 0; $i < $marketOrders; $i++) {
            $store = $stores[$i % count($stores)];
            $offers = DB::table('offers')->join('products','products.id','=','offers.product_id')
                ->where('offers.store_id', $store)->orderBy('offers.id')
                ->get(['offers.id','offers.product_id','offers.price_cents','offers.currency','products.name','products.sku','products.unit']);
            $lines = $offers->slice($i % 3, 2)->values();
            $subtotal = 0;
            foreach ($lines as $line) {
                $subtotal += Fixture::qtyTotal($line->price_cents, (3 + ($i % 12)) * 1000);
            }
            $placed = $now->subDays(340 - $i * 2)->setTime(10 + $i % 6, 30);
            $status = $i % 13 === 0 ? 'cancelled' : ($i > 156 ? 'placed' : ($i > 143 ? 'confirmed' : ($i > 131 ? 'ready' : 'delivered')));
            $orderId = Fixture::insert('market_orders', Fixture::stamped([
                'number' => sprintf('MO-%d-W%04d', $placed->year, $i+1),
                'buyer_id' => $buyers[$i % count($buyers)], 'store_id' => $store,
                'status' => $status, 'currency' => 'AZN', 'total_cents' => $subtotal,
                'deliver_to' => ['Bakı','Sumqayıt','Gəncə'][$i%3],
                'note' => 'Sifariş edilmiş məhsullar kataloq təkliflərindən seçilib.',
                'confirmed_at' => in_array($status,['confirmed','ready','delivered'],true) ? $placed->addDay() : null,
                'ready_at' => in_array($status,['ready','delivered'],true) ? $placed->addDays(2) : null,
                'delivered_at' => $status === 'delivered' ? $placed->addDays(4) : null,
                'cancelled_at' => $status === 'cancelled' ? $placed->addHours(3) : null,
                'cancel_reason' => $status === 'cancelled' ? 'Tələb dəyişdirildi.' : null,
            ], $placed), true);
            foreach ($lines as $line) {
                $quantity = (3+$i%12)*1000;
                Fixture::insert('market_order_items', Fixture::stamped([
                    'market_order_id' => $orderId, 'offer_id' => $line->id,
                    'product_id' => $line->product_id, 'name' => $line->name, 'sku' => $line->sku,
                    'unit' => $line->unit, 'quantity_milli' => $quantity, 'unit_price_cents' => $line->price_cents,
                    'total_cents' => Fixture::qtyTotal($line->price_cents,$quantity),
                ], $placed));
            }
            if (in_array($status,['ready','delivered'],true)) {
                foreach (['pick','deliver'] as $type) {
                    Fixture::insert('worker_tasks', Fixture::stamped([
                        'market_order_id' => $orderId, 'store_id' => $store, 'type' => $type,
                        'status' => $status === 'delivered' || $type === 'pick' ? 'done' : 'open',
                        'assigned_to' => $workers[$store] ?? null,
                        'done_by' => $status === 'delivered' || $type === 'pick' ? ($workers[$store] ?? null) : null,
                        'done_at' => $status === 'delivered' || $type === 'pick' ? $placed->addDays($type === 'pick' ? 2 : 4) : null,
                    ], $placed));
                }
            }
        }
        // Non-checkout carts remain only for genuinely available offers.
        foreach (array_slice($buyers,0,30) as $i => $buyer) {
            $offer = DB::table('offers')->orderBy('id')->offset($i*2)->first(['id']);
            Fixture::insert('cart_items', Fixture::stamped([
                'user_id' => $buyer, 'offer_id' => $offer->id, 'quantity_milli' => (1+$i%4)*1000,
            ], $now));
        }
    }

    private function contracts(): void
    {
        $now = Fixture::now();
        $orders = DB::table('purchase_orders')->where('status','invoiced')->orderBy('id')->get();
        $count = 0;
        foreach ($orders as $index => $po) {
            if ($index % 3 !== 0) continue;
            $lines = DB::table('purchase_order_items')->where('purchase_order_id',$po->id)->get();
            $begins = CarbonImmutable::parse($po->created_at)->addDay();
            $ceiling = $po->total_cents * 3;
            $contractId = Fixture::insert('contracts', Fixture::stamped([
                'number' => 'CT-W'.str_pad((string)($index+1),5,'0',STR_PAD_LEFT),
                'supplier_id' => $po->supplier_id, 'owner_id' => $po->buyer_id, 'rfq_id' => $po->rfq_id,
                'title' => 'Çərçivə təchizat müqaviləsi — '.$po->number,
                'status' => $index % 6 === 0 ? 'expired' : 'active', 'currency' => 'AZN',
                'ceiling_cents' => $ceiling, 'released_cents' => $po->total_cents,
                'starts_on' => $begins->toDateString(),
                'ends_on' => $begins->addMonths($index%6===0?6:24)->toDateString(),
                'payment_terms' => 'Net 30',
                'terms' => 'Razılaşdırılmış məhsul nomenklaturası üzrə təkrar sifariş şərtləri.',
            ], $begins), true);
            $releaseId = Fixture::insert('contract_releases', Fixture::stamped([
                'number' => 'CR-W'.str_pad((string)($index+1),5,'0',STR_PAD_LEFT),
                'contract_id' => $contractId, 'created_by' => $po->buyer_id,
                'status' => 'delivered', 'total_cents' => $po->total_cents,
                'needed_by' => $begins->addDays(30)->toDateString(),
                'note' => 'Qrafik üzrə birinci sifariş',
                'accepted_at' => $begins->addDay(), 'delivered_at' => $begins->addDays(14),
            ], $begins), true);
            foreach ($lines as $line) {
                $itemId = Fixture::insert('contract_items', Fixture::stamped([
                    'contract_id' => $contractId, 'name' => $line->name, 'unit' => $line->unit,
                    'unit_price_cents' => $line->unit_price_cents,
                ], $begins));
                Fixture::insert('contract_release_items', Fixture::stamped([
                    'contract_release_id' => $releaseId, 'contract_item_id' => $itemId,
                    'name' => $line->name, 'unit' => $line->unit,
                    'quantity_milli' => $line->quantity_milli,
                    'unit_price_cents' => $line->unit_price_cents,
                    'total_cents' => $line->total_cents,
                ], $begins));
            }
            $count++;
        }
        Fixture::note('Contracts', "{$count} contracts with itemized releases linked to awarded RFQs.");
    }

    private function supplierAssurance(): void
    {
        $now = Fixture::now();
        $admin = DB::table('users')->where('role','admin')->value('id');
        $buyers = DB::table('users')->where('role','buyer')->pluck('id')->all();
        $suppliers = DB::table('suppliers')->orderBy('id')->get(['id','status','name']);
        $workers = [];
        foreach ($suppliers as $i => $supplier) {
            if ($i % 3 === 0) {
                $workers[$supplier->id] = Fixture::insert('users', Fixture::stamped([
                    'name' => BusinessIdentitySeeder::person($i+410),
                    'email' => "workflow.field-worker.$i@example.test", 'password' => Fixture::passwordHash(),
                    'role' => 'supplier_worker', 'supplier_id' => $supplier->id,
                    'status' => 'active', 'email_verified_at' => $now,
                ], $now), true);
            }
            foreach (['registration','tax','bank'] as $type) {
                $filename = "supplier-documents/workflow/{$supplier->id}/{$type}.txt";
                $contents = "48SOURCE SYNTHETIC FIXTURE — NOT A LEGAL DOCUMENT\nSupplier: {$supplier->name}\nType: {$type}\nPurpose: local UI and workflow testing only.\n";
                Storage::disk('local')->put($filename,$contents);
                Fixture::insert('supplier_documents', Fixture::stamped([
                    'supplier_id' => $supplier->id, 'type' => $type,
                    'title' => ucfirst($type).' — reference sample',
                    'path' => $filename, 'original_name' => $type.'-sample.txt',
                    'expires_on' => $now->addMonths(12)->toDateString(),
                    'status' => $supplier->status === 'active' ? 'verified' : 'pending',
                    'uploaded_by' => DB::table('users')->where('supplier_id',$supplier->id)->where('role','supplier')->value('id'),
                    'verified_by' => $supplier->status === 'active' ? $admin : null,
                ], $now));
            }
            if ($supplier->status !== 'active') continue;
            for ($month=0; $month<3; $month++) {
                $date = $now->subMonths($month)->startOfMonth();
                $quality = 3 + ($i+$month)%3;
                $delivery = 3 + ($i*2+$month)%3;
                $price = 3 + ($i+$month*3)%3;
                $service = 3 + ($i*3+$month)%3;
                Fixture::insert('supplier_scorecards', Fixture::stamped([
                    'supplier_id' => $supplier->id, 'period' => $date->format('Y-m'),
                    'quality' => $quality, 'delivery' => $delivery, 'price' => $price, 'service' => $service,
                    'score_tenths' => (int)round(($quality+$delivery+$price+$service)*2.5),
                    'comment' => 'Sifariş uyğunluğu və çatdırılma göstəricilərinin aylıq qiymətləndirilməsi.',
                    'created_by' => $buyers[$i % count($buyers)],
                ], $date));
            }
            if ($i%9===0) {
                Fixture::insert('supplier_actions', Fixture::stamped([
                    'supplier_id' => $supplier->id, 'title' => 'Çatdırılma performansının yaxşılaşdırılması',
                    'detail' => 'Gecikmələrin səbəblərini araşdırmaq və tədbir planı vermək.',
                    'due_on' => $now->addDays(21)->toDateString(),
                    'status' => $i%2===0 ? 'done':'open',
                    'owner_id' => $buyers[$i%count($buyers)],
                    'done_at' => $i%2===0 ? $now->subDays(3) : null,
                ], $now));
            }
            if ($i % 4===0) {
                $rfq = DB::table('rfqs')->where('status','awarded')->offset($i)->first();
                $assigned = $workers[$supplier->id] ?? null;
                Fixture::insert('field_assessments', Fixture::stamped([
                    'number' => sprintf('FA-W%05d',$i+1),
                    'supplier_id' => $supplier->id, 'rfq_id' => $rfq?->id,
                    'requested_by' => $buyers[$i%count($buyers)], 'assigned_to' => $assigned,
                    'title' => 'Təchizatçı sahəsinin keyfiyyət yoxlaması',
                    'checklist' => json_encode(['storage','safety','capacity'],JSON_UNESCAPED_UNICODE),
                    'answers' => json_encode(['storage'=>'pass','safety'=>'pass','capacity'=>'pass']),
                    'findings' => 'Anbar tutumu və təhlükəsizlik tədbirləri yerində qiymətləndirildi.',
                    'status' => 'approved', 'due_on' => $now->subDays(10)->toDateString(),
                    'submitted_at' => $now->subDays(13), 'decided_by' => $admin,
                    'decision_note' => 'Yoxlama yekunlaşdırıldı.', 'decided_at' => $now->subDays(9),
                ], $now->subDays(20)), true);
            }
        }
    }

    private function sourcingActivity(): void
    {
        $now = Fixture::now();
        $rfqs = DB::table('rfqs')->whereIn('status',['closed','awarded'])->orderBy('id')->get();
        $users = DB::table('users')->where('role','supplier')->pluck('id','supplier_id');
        foreach ($rfqs as $i => $rfq) {
            $supplier = DB::table('rfq_invitations')->where('rfq_id',$rfq->id)->first();
            if (!$supplier) continue;
            $person = (int) $users[$supplier->supplier_id];
            if ($i%2 === 0) {
                Fixture::insert('rfq_questions', Fixture::stamped([
                    'rfq_id' => $rfq->id, 'supplier_id' => $supplier->supplier_id,
                    'asked_by' => $person, 'question' => 'Çatdırılma zamanı və qablaşdırma tələblərini dəqiqləşdirin.',
                    'answer' => 'Ünvan və vaxt sifariş sənədinə əsasən razılaşdırılacaq.',
                    'answered_by' => $rfq->buyer_id, 'answered_at' => CarbonImmutable::parse($rfq->created_at)->addDays(3),
                ], CarbonImmutable::parse($rfq->created_at)->addDays(2)));
            }
            if ($i%5 === 0) {
                $deadline = CarbonImmutable::parse($rfq->created_at)->addDays(9);
                $rfi = Fixture::insert('rfis', Fixture::stamped([
                    'number' => sprintf('RFI-W%05d',$i+1),
                    'buyer_id' => $rfq->buyer_id, 'rfq_id' => $rfq->id,
                    'title' => 'Texniki uyğunluq sorğusu — '.$rfq->number,
                    'description' => 'Çatdırılma imkanları, sertifikat və texniki göstəricilər.',
                    'questions' => json_encode(['delivery'=>'Çatdırılma müddəti?','warranty'=>'Zəmanət?']),
                    'status' => 'closed', 'deadline_at' => $deadline,
                ], CarbonImmutable::parse($rfq->created_at)), true);
                Fixture::insert('rfi_responses', Fixture::stamped([
                    'rfi_id' => $rfi, 'supplier_id' => $supplier->supplier_id,
                    'status' => 'answered',
                    'answers' => json_encode(['delivery'=>'10 iş günü','warranty'=>'12 ay']),
                    'answered_by' => $person, 'answered_at' => $deadline->subDays(3),
                ], $deadline->subDays(3)));
            }
            if ($i%13 === 0) {
                $opens = CarbonImmutable::parse($rfq->closed_at)->addHours(2);
                $auction = Fixture::insert('auctions', Fixture::stamped([
                    'rfq_id' => $rfq->id, 'opened_by' => $rfq->buyer_id,
                    'status' => 'ended', 'starts_at' => $opens, 'ends_at' => $opens->addHours(2),
                    'min_step_bp' => 50, 'extend_minutes' => 2,
                ], $opens),true);
                $quote = DB::table('quotes')->where('rfq_id',$rfq->id)->first();
                if ($quote) {
                    Fixture::insert('auction_bids', Fixture::stamped([
                        'auction_id' => $auction, 'supplier_id' => $quote->supplier_id,
                        'user_id' => (int)$users[$quote->supplier_id], 'total_cents' => $quote->total_cents,
                    ], $opens->addMinutes(30)));
                }
            }
            if ($i%11 === 0) {
                $started = CarbonImmutable::parse($rfq->closed_at)->addHours(1);
                Fixture::insert('bafo_rounds', Fixture::stamped([
                    'rfq_id' => $rfq->id, 'round' => 2, 'opened_by' => $rfq->buyer_id,
                    'status' => 'closed', 'deadline_at' => $started->addDays(2),
                    'message' => 'Yekun qiymət təklifinin təsdiqi.',
                    'closed_at' => $started->addDays(2),
                ], $started), true);
            }
        }
    }

    private function communication(): void
    {
        $rfqs = DB::table('rfqs')->whereIn('status',['open','closed','awarded'])->orderBy('id')->get();
        $supplierUsers = DB::table('users')->where('role','supplier')->pluck('id','supplier_id');
        $count = 0;
        foreach ($rfqs as $i => $rfq) {
            $inv = DB::table('rfq_invitations')->where('rfq_id',$rfq->id)->first();
            if (!$inv) continue;
            $supplierUser = (int)$supplierUsers[$inv->supplier_id];
            $opened = CarbonImmutable::parse($rfq->created_at)->addDays(2);
            $conv = Fixture::insert('conversations',Fixture::stamped([
                'supplier_id' => $inv->supplier_id, 'created_by' => $rfq->buyer_id,
                'subject' => $rfq->number.' — çatdırılma və spesifikasiya',
                'subject_ref_type' => Rfq::class, 'subject_ref_id' => $rfq->id,
                'last_message_at' => $opened->addDays(2),
            ],$opened),true);
            $last = 0;
            foreach ([
                [$rfq->buyer_id,'Xahiş edirik, çatdırılma qrafiki və məhsul göstəricilərini təsdiqləyin.'],
                [$supplierUser,'Qiymət təklifinə çatdırılma şərtlərini və uyğunluq məlumatlarını əlavə etdik.'],
                [$rfq->buyer_id,'Təşəkkürlər. Qiymətləndirmə mərhələsində nəzərə alınacaq.'],
            ] as $m => [$user,$body]) {
                $last = Fixture::insert('messages', Fixture::stamped([
                    'conversation_id' => $conv, 'user_id' => $user, 'body' => $body,
                ],$opened->addDays($m)));
            }
            foreach ([$rfq->buyer_id,$supplierUser] as $userId) {
                Fixture::insert('conversation_reads', [
                    'conversation_id' => $conv, 'user_id' => $userId,
                    'message_id' => $userId === $rfq->buyer_id ? $last : $last-1,
                    'read_at' => $opened->addDays(2),
                ]);
            }
            if ($i%4===0) {
                $filename = "attachments/rfq/{$rfq->id}/requirements.txt";
                $contents = "48SOURCE SYNTHETIC WORKFLOW FILE — NOT A COMMERCIAL DOCUMENT\nRFQ: {$rfq->number}\nRequirements: {$rfq->requirements}\n";
                Storage::disk('local')->put($filename,$contents);
                Fixture::insert('attachments', Fixture::stamped([
                    'attachable_type' => Rfq::class, 'attachable_id' => $rfq->id,
                    'uploaded_by' => $rfq->buyer_id, 'supplier_id' => null,
                    'path' => $filename, 'name' => 'requirements.txt',
                    'size' => strlen($contents),
                ],$opened));
            }
            Fixture::insert('activity_logs',[
                'user_id' => $rfq->buyer_id, 'actor_name' => DB::table('users')->where('id',$rfq->buyer_id)->value('name'),
                'area' => 'RFQ', 'action' => 'Published '.$rfq->number,
                'subject_type' => Rfq::class, 'subject_id' => $rfq->id,
                'subject_label' => $rfq->number, 'supplier_id' => null,
                'meta' => json_encode(['source'=>'synthetic-workflow-v2']),
                'created_at' => $opened,
            ]);
            $notificationUser = $supplierUser;
            DB::table('notifications')->insert(Fixture::stamped([
                'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\WorkflowMessage',
                'notifiable_type' => 'App\\Models\\User','notifiable_id' => $notificationUser,
                'data' => json_encode(['title'=>'New message','body'=>'Sourcing request '.$rfq->number,
                    'url'=>'/messages?c='.$conv,'area'=>'Messages']),
                'read_at' => $i%3===0 ? null : $opened->addDays(3),
            ],$opened));
            if ($i%3===0) {
                Fixture::insert('outbox_emails', Fixture::stamped([
                    'user_id' => $supplierUser, 'to' => DB::table('users')->where('id',$supplierUser)->value('email'),
                    'subject' => '48Source RFQ notification '.$rfq->number,
                    'body' => 'Synthetic email fixture — no mail was sent.',
                    'area' => 'Sourcing', 'status' => 'queued', 'sent_at' => null,
                ],$opened));
            }
            if ($i%5 === 0) {
                Fixture::insert('reminders', Fixture::stamped([
                    'key' => 'fixture:rfq:'.$rfq->id.':supplier:'.$inv->supplier_id,
                ],$opened));
            }
            $count++;
        }
        Fixture::note('Communication', "{$count} conversations, each with 3 messages and read markers; RFQ documents, audit, notices and outbox history.");
    }

    private function budgets(): void
    {
        $year = (int)Fixture::now()->year;
        $categories = DB::table('categories')->whereNull('parent_id')->get(['id']);
        foreach ($categories as $category) {
            foreach ([$year-1,$year] as $y) {
                Fixture::insert('budgets', Fixture::stamped([
                    'year' => $y, 'category_id' => $category->id,
                    'amount_cents' => 250000000 + $category->id * 17000000,
                ]));
            }
        }
    }
    private function sequences(): void
    {
        // Keep live Sequence::next(...) counters above all seeded W-prefixed examples.
        // W-references are distinct, but counters are initialized for regular user actions.
        $year = (int) Fixture::now()->year;
        foreach (['RFQ','PO','REQ','INV','CT','CR','MO','RFI','FA'] as $type) {
            foreach ([$year-1,$year] as $y) {
                DB::table('sequences')->updateOrInsert(
                    ['name' => $type,'year' => $y],
                    ['name' => $type,'year' => $y,'value' => 500]
                );
            }
        }
    }

}
