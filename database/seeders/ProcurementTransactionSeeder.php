<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\Fixture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcurementTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Fixture::now();
        $buyers = DB::table('users')->where('role', 'buyer')->orderBy('id')->pluck('id')->all();
        $products = DB::table('products')->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('offers', 'products.id', '=', 'offers.product_id')
            ->select('products.id', 'products.name', 'products.unit', 'products.category_id', 'categories.parent_id',
                DB::raw('MIN(offers.price_cents) as reference_price'))
            ->groupBy('products.id', 'products.name', 'products.unit', 'products.category_id', 'categories.parent_id')
            ->get()->groupBy('parent_id');
        $supplierGroups = DB::table('suppliers')->where('status', 'active')->get(['id', 'category_id'])
            ->groupBy('category_id');
        $userBySupplier = DB::table('users')->where('role', 'supplier')->pluck('id','supplier_id');
        $groups = $products->keys()->values();
        $awards = 0;
        $rfqCount = 240;

        for ($i = 0; $i < $rfqCount; $i++) {
            $age = 365 - (int) floor($i * 1.5);
            $start = $now->subDays($age)->setTime(9 + $i % 7, 15);
            $deadline = $start->addDays(14);
            $group = $groups[$i % $groups->count()];
            $catalog = $products->get($group)->values();
            $selection = [];
            // Exactly three DISTINCT real-world product types, all from a supplier's specialty.
            for ($n = 0; $n < 3; $n++) {
                $p = $catalog[($i * 3 + $n) % $catalog->count()];
                $qty = (2 + (($i * 7 + $n * 11) % 64)) * 1000;
                $price = (int) $p->reference_price;
                $selection[] = compact('p','qty','price');
            }
            $buyer = $buyers[$i % count($buyers)];
            $status = match (true) {
                $age <= 9 => 'draft',
                $age <= 19 => 'open',
                $age <= 33 => 'closed',
                $i % 19 === 0 => 'cancelled',
                $i % 8 === 0 => 'closed',
                default => 'awarded',
            };
            $reference = sprintf('RFQ-%d-W%04d', $start->year, $i+1);
            $title = $selection[0]['p']->name.' və əlaqəli təchizat — '.[
                'Bakı əsas anbarı', 'Sumqayıt istehsalat sahəsi', 'Gəncə xidmət mərkəzi',
                'Şimal logistika mərkəzi', 'Yeni inzibati bina',
            ][$i % 5];
            $budget = array_sum(array_map(fn ($x) => Fixture::qtyTotal($x['price'], $x['qty']), $selection));

            // Requisition is always created first. The RFQ references its source material.
            $reqId = Fixture::insert('requisitions', Fixture::stamped([
                'number' => sprintf('REQ-%d-W%04d', $start->year, $i+1),
                'requester_id' => $buyer, 'category_id' => $selection[0]['p']->category_id,
                'title' => $title, 'justification' => 'Təsdiq edilmiş ehtiyac: əməliyyat və layihə təchizatı.',
                'needed_by' => $deadline->addDays(35)->toDateString(), 'currency' => 'AZN',
                'total_cents' => $budget, 'status' => $status === 'draft' ? 'draft' : 'converted',
                'submitted_at' => $status === 'draft' ? null : $start->addHour(),
                'decided_at' => $status === 'draft' ? null : $start->addHours(5),
            ], $start), true);
            foreach ($selection as $x) {
                Fixture::insert('requisition_items', Fixture::stamped([
                    'requisition_id' => $reqId, 'name' => $x['p']->name,
                    'quantity_milli' => $x['qty'], 'unit' => $x['p']->unit,
                    'est_price_cents' => $x['price'], 'total_cents' => Fixture::qtyTotal($x['price'], $x['qty']),
                ], $start));
            }
            if ($status !== 'draft') {
                Fixture::insert('requisition_approvals', Fixture::stamped([
                    'requisition_id' => $reqId, 'step' => 1, 'role' => 'buyer',
                    'label' => 'Procurement approval', 'status' => 'approved',
                    'approver_id' => $buyer, 'comment' => 'Ehtiyac və büdcə təsdiq edildi.',
                    'decided_at' => $start->addHours(5),
                ], $start));
            }

            $closed = in_array($status, ['closed','awarded'], true) ? $deadline->addHour() : null;
            $awardedAt = $status === 'awarded' ? $deadline->addDays(2) : null;
            $rfqId = Fixture::insert('rfqs', Fixture::stamped([
                'number' => $reference, 'title' => $title,
                'description' => 'Təsdiqlənmiş tələbnaməyə əsaslanan məhsul və xidmətlərin satınalınması.',
                'type' => $i % 8 === 0 ? 'public' : 'invited',
                'category_id' => $selection[0]['p']->category_id, 'buyer_id' => $buyer,
                'status' => $status, 'currency' => 'AZN', 'budget_cents' => $budget,
                'deadline_at' => $deadline, 'delivery_location' => ['Bakı','Sumqayıt','Gəncə'][$i % 3],
                'required_by' => $deadline->addDays(35)->toDateString(),
                'requirements' => 'Texniki uyğunluq, orijinal sənədlər, çatdırılma və zəmanət tələbləri.',
                'payment_terms' => 'Net 30', 'scoring_preset' => ['balanced','price','quality'][$i%3],
                'sealed' => $i % 7 === 0, 'published_at' => $status === 'draft' ? null : $start->addDay(),
                'closed_at' => $closed, 'awarded_at' => $awardedAt,
                'cancelled_at' => $status === 'cancelled' ? $start->addDays(4) : null,
                'cancel_reason' => $status === 'cancelled' ? 'Layihə büdcəsi yenidən nəzərdən keçirilir.' : null,
            ], $start), true);
            DB::table('requisitions')->where('id', $reqId)->update(['rfq_id' => $rfqId]);

            $rfqItems = [];
            foreach ($selection as $n => $x) {
                $id = Fixture::insert('rfq_items', Fixture::stamped([
                    'rfq_id' => $rfqId, 'position' => $n+1, 'name' => $x['p']->name,
                    'description' => 'Məhsul spesifikasiyası: '.$x['p']->name,
                    'quantity_milli' => $x['qty'], 'unit' => $x['p']->unit,
                    'target_price_cents' => $x['price'],
                ], $start));
                $rfqItems[] = ['id' => $id, 'name' => $x['p']->name, 'qty' => $x['qty'],
                    'unit' => $x['p']->unit, 'price' => $x['price']];
            }

            if (in_array($status, ['draft', 'cancelled'], true)) {
                continue;
            }

            $suppliers = $supplierGroups->get($group)->values();
            $bidders = [];
            for ($bidder = 0; $bidder < 3; $bidder++) {
                $supplier = $suppliers[($i*3+$bidder) % $suppliers->count()];
                $supplierUser = (int) $userBySupplier[$supplier->id];
                $replied = $start->addDays(3+$bidder);
                $pending = $status === 'open' && $bidder === 2;
                Fixture::insert('rfq_invitations', Fixture::stamped([
                    'rfq_id' => $rfqId, 'supplier_id' => $supplier->id,
                    'status' => $pending ? 'pending' : 'accepted',
                    'responded_at' => $pending ? null : $replied,
                ], $start));
                if ($pending) {
                    continue;
                }
                $multiplier = [10000,9650,10350][$bidder] + (($i % 5) - 2) * 45;
                $lines = [];
                $total = 0;
                foreach ($rfqItems as $item) {
                    $price = intdiv($item['price'] * $multiplier + 5000, 10000);
                    $sum = Fixture::qtyTotal($price, $item['qty']);
                    $total += $sum;
                    $lines[] = ['item' => $item, 'price' => $price, 'total' => $sum];
                }
                $quoteStatus = $status === 'awarded' ? ($bidder === 1 ? 'won' : 'lost') : 'submitted';
                $quoteId = Fixture::insert('quotes', Fixture::stamped([
                    'rfq_id' => $rfqId, 'supplier_id' => $supplier->id,
                    'submitted_by' => $supplierUser, 'status' => $quoteStatus, 'currency' => 'AZN',
                    'delivery_days' => 7 + $bidder*3, 'payment_terms' => 'Net 30',
                    'valid_until' => $deadline->addDays(30)->toDateString(),
                    'notes' => 'Standart zəmanət, qablaşdırma və çatdırılma daxildir.',
                    'total_cents' => $total, 'revision' => $i % 5 === 0 ? 1 : 0,
                    'submitted_at' => $replied->addHours(2),
                ], $replied), true);
                foreach ($lines as $line) {
                    Fixture::insert('quote_items', Fixture::stamped([
                        'quote_id' => $quoteId, 'rfq_item_id' => $line['item']['id'],
                        'unit_price_cents' => $line['price'], 'total_cents' => $line['total'],
                    ], $replied));
                }
                $bidders[] = ['id' => $quoteId, 'supplier_id' => $supplier->id,
                    'user_id' => $supplierUser, 'lines' => $lines, 'total' => $total];
            }
            if ($status !== 'awarded') {
                continue;
            }
            $this->award($rfqId, $reference, $buyer, $deadline, $awardedAt, $age, $bidders[1], $i);
            $awards++;
        }

        Fixture::note('Procurement', "{$rfqCount} requisitions/RFQs sourced from products; {$awards} awarded orders, itemized bids and invoices.");
    }

    private function award(int $rfqId, string $reference, int $buyer, CarbonImmutable $deadline,
        CarbonImmutable $award, int $age, array $winner, int $index): void
    {
        $status = match (true) {
            $age <= 43 => 'draft',
            $age <= 58 => 'issued',
            $age <= 75 => 'acknowledged',
            $age <= 95 => 'in_transit',
            $age <= 115 => 'delivered',
            default => 'invoiced',
        };
        $issued = $status !== 'draft' ? $award->addDay() : null;
        $ack = in_array($status, ['acknowledged','in_transit','delivered','invoiced'], true) ? $award->addDays(3) : null;
        $shipped = in_array($status, ['in_transit','delivered','invoiced'], true) ? $award->addDays(6) : null;
        $delivered = in_array($status, ['delivered','invoiced'], true) ? $award->addDays(14) : null;
        $invoiced = $status === 'invoiced' ? $award->addDays(18) : null;
        $orderId = Fixture::insert('purchase_orders', Fixture::stamped([
            'number' => str_replace('RFQ-','PO-',$reference), 'rfq_id' => $rfqId,
            'supplier_id' => $winner['supplier_id'], 'quote_id' => $winner['id'],
            'buyer_id' => $buyer, 'status' => $status, 'currency' => 'AZN',
            'total_cents' => $winner['total'], 'payment_terms' => 'Net 30',
            'delivery_location' => ['Bakı','Sumqayıt','Gəncə'][$index % 3],
            'expected_at' => $deadline->addDays(32)->toDateString(),
            'award_justification' => 'Texniki uyğunluq, qiymət və çatdırılma müddətinə əsasən.',
            'issued_at' => $issued, 'acknowledged_at' => $ack, 'shipped_at' => $shipped,
            'delivered_at' => $delivered, 'invoiced_at' => $invoiced,
        ], $award), true);
        $orderLines = [];
        foreach ($winner['lines'] as $line) {
            $item = $line['item'];
            $orderLines[] = ['id' => Fixture::insert('purchase_order_items', Fixture::stamped([
                'purchase_order_id' => $orderId, 'rfq_item_id' => $item['id'],
                'name' => $item['name'], 'quantity_milli' => $item['qty'],
                'unit' => $item['unit'], 'unit_price_cents' => $line['price'], 'total_cents' => $line['total'],
            ], $award)), 'line' => $line];
        }
        if ($delivered) {
            $gr = Fixture::insert('goods_receipts', Fixture::stamped([
                'number' => str_replace('RFQ-','GR-',$reference),
                'purchase_order_id' => $orderId, 'received_by' => $buyer,
                'received_on' => $delivered->toDateString(), 'note' => 'Qəbul edilmiş mallar yoxlanıldı.',
            ], $delivered), true);
            foreach ($orderLines as $l) {
                Fixture::insert('goods_receipt_items', Fixture::stamped([
                    'goods_receipt_id' => $gr, 'purchase_order_item_id' => $l['id'],
                    'accepted_milli' => $l['line']['item']['qty'],
                    'rejected_milli' => 0,
                ], $delivered));
            }
        }
        if ($invoiced) {
            $inv = Fixture::insert('invoices', Fixture::stamped([
                'number' => str_replace('RFQ-','INV-',$reference),
                'supplier_ref' => sprintf('WF-%05d', $index+1),
                'purchase_order_id' => $orderId, 'supplier_id' => $winner['supplier_id'],
                'submitted_by' => $winner['user_id'], 'status' => $age > 145 ? 'paid' : 'approved',
                'currency' => 'AZN', 'total_cents' => $winner['total'],
                'issued_on' => $invoiced->toDateString(), 'due_on' => $invoiced->addDays(30)->toDateString(),
                'decided_by' => $buyer, 'decided_at' => $invoiced->addDay(),
                'paid_at' => $age > 145 ? $invoiced->addDays(18) : null,
            ], $invoiced), true);
            foreach ($orderLines as $l) {
                Fixture::insert('invoice_items', Fixture::stamped([
                    'invoice_id' => $inv, 'purchase_order_item_id' => $l['id'],
                    'quantity_milli' => $l['line']['item']['qty'],
                    'unit_price_cents' => $l['line']['price'], 'total_cents' => $l['line']['total'],
                ], $invoiced));
            }
        }
    }
}
