<?php

namespace App\Livewire\Orders;

use App\Livewire\Concerns\Page;
use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use Page;

    public string $number;

    public string $reason = '';

    /** Receipt form: order item id => quantity typed. */
    public array $acc = [];

    public array $rej = [];

    public string $rdate = '';

    public string $rnote = '';

    /** Invoice form. */
    public array $inv = ['supplier_ref' => '', 'issued_on' => '', 'due_on' => ''];

    public array $il = [];

    public function mount(string $number): void
    {
        $this->number = $number;
        $this->order();
    }

    protected function order(): PurchaseOrder
    {
        return PurchaseOrder::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    public function advance(PurchaseOrderService $orders): void
    {
        $this->need('orders', 'E');
        $po = $this->order();
        $this->attempt(fn () => $orders->advance($po, $this->me()), "{$po->number} updated.");
    }

    public function cancel(PurchaseOrderService $orders): void
    {
        $this->need('orders', 'E');
        if ($this->attempt(fn () => $orders->cancel($this->order(), $this->me(), $this->reason), 'Order cancelled.')) {
            $this->reset('reason');
            $this->close();
        }
    }

    public function openReceipt(\App\Services\ReceiptService $receipts): void
    {
        $this->need('invoices', 'C');
        $po = $this->order()->load('items');
        $got = $receipts->received($po);
        $this->acc = $this->rej = [];
        foreach ($po->items as $it) {
            $this->acc[$it->id] = \App\Support\Money::qtyInput(max(0, $it->quantity_milli - $got[$it->id]));
        }
        $this->rdate = now()->toDateString();
        $this->rnote = '';
        $this->open('receipt');
    }

    public function receive(\App\Services\ReceiptService $receipts): void
    {
        $this->need('invoices', 'C');
        $grn = null;
        if ($this->attempt(function () use ($receipts, &$grn) {
            $grn = $receipts->record($this->order()->load('items'), $this->me(), $this->acc, $this->rej, $this->rdate, $this->rnote);
        })) {
            $this->toast("Receipt {$grn->number} recorded.");
            $this->modal = null;
        }
    }

    public function openInvoice(\App\Services\ReceiptService $receipts, \App\Services\InvoiceService $invoices): void
    {
        $this->need('invoices', 'C');
        $po = $this->order()->load('items');
        $got = $receipts->received($po);
        $done = $invoices->invoiced($po);
        $this->il = [];
        foreach ($po->items as $it) {
            $this->il[$it->id] = ['quantity' => \App\Support\Money::qtyInput(max(0, $got[$it->id] - ($done[$it->id] ?? 0))), 'price' => \App\Support\Money::input($it->unit_price_cents)];
        }
        $this->inv = ['supplier_ref' => '', 'issued_on' => now()->toDateString(), 'due_on' => now()->addDays(30)->toDateString()];
        $this->open('invoice');
    }

    public function sendInvoice(\App\Services\InvoiceService $invoices)
    {
        $this->need('invoices', 'C');
        $doc = null;
        if ($this->attempt(function () use ($invoices, &$doc) {
            $doc = $invoices->submit($this->order()->load('items'), $this->me(), $this->inv, $this->il);
        })) {
            session()->flash('toast', $doc->status === 'matched' ? 'Invoice sent. It matches the order and receipts.' : 'Invoice sent. The buyer will check the differences.');

            return $this->redirectRoute('invoices.show', $doc->number, navigate: true);
        }
    }

    public string $msg = '';

    public function toContract(\App\Services\ContractService $contracts)
    {
        $this->need('contracts', 'C');
        abort_unless($this->me()->isSourcing(), 403);
        $c = null;
        if ($this->attempt(function () use ($contracts, &$c) {
            $c = $contracts->fromOrder($this->order()->load('items', 'supplier', 'rfq'), $this->me());
        })) {
            session()->flash('toast', "Draft contract {$c->number} created from this order. Set the period and ceiling, then activate it.");

            return $this->redirectRoute('contracts.edit', $c->number, navigate: true);
        }
    }

    public function sendMessage(\App\Services\MessageService $messages)
    {
        $po = $this->order();
        $conv = null;
        if ($this->attempt(function () use ($messages, $po, &$conv) {
            $conv = $messages->start($this->me(), $po->supplier, "About {$po->number}", $this->msg, $po);
        })) {
            return $this->redirectRoute('messages.index', ['c' => $conv->id], navigate: true);
        }
    }

    public function render(PurchaseOrderService $orders)
    {
        $po = $this->order()->load('items', 'supplier', 'rfq', 'buyer');
        $u = $this->me();
        $step = PurchaseOrder::NEXT[$po->status] ?? null;
        $waiting = match (true) {
            $po->status === 'cancelled' => 'This order was cancelled.',
            $po->status === 'invoiced' => 'Invoiced. Nothing more to do on this order.',
            $step === null => '',
            $orders->nextFor($po, $u) !== null => 'Your turn: '.mb_strtolower($step['label']).'.',
            default => 'Waiting for the '.($step['side'] === 'buyer' ? 'buyer' : 'supplier').' to '.mb_strtolower($step['label']).'.',
        };

        $receipts = app(\App\Services\ReceiptService::class);
        $ids = PurchaseOrder::visibleTo($u)->orderByDesc('id')->pluck('number')->all();
        $n = array_search($po->number, $ids, true);

        return view('livewire.orders.show', [
            'pager' => $n === false ? null : ['n' => $n + 1, 'of' => count($ids), 'prev' => $n > 0 ? route('orders.show', $ids[$n - 1]) : null, 'next' => $n < count($ids) - 1 ? route('orders.show', $ids[$n + 1]) : null],
            'received' => $receipts->received($po), 'pct' => $receipts->percent($po), 'grns' => $po->receipts()->with('receiver', 'items')->latest('id')->get(), 'invoices' => $u->allowed('invoices') ? $po->invoices()->latest('id')->get() : collect(),
            'canReceive' => $u->isSourcing() && $u->allowed('invoices', 'C') && in_array($po->status, ['acknowledged', 'in_transit', 'delivered'], true) && ($u->isAdmin() || $po->buyer_id === $u->id),
            'canInvoice' => $u->role === 'supplier' && $u->allowed('invoices', 'C') && in_array($po->status, ['acknowledged', 'in_transit', 'delivered', 'invoiced'], true),
            'po' => $po, 'next' => $orders->nextFor($po, $u), 'waiting' => $waiting,
            'canCancel' => $u->allowed('orders', 'E') && in_array($po->status, ['draft', 'issued'], true) && ($u->isAdmin() || $po->buyer_id === $u->id),
            'logs' => ActivityLog::where('subject_type', 'purchase_order')->where('subject_id', $po->id)->latest('created_at')->get(),
        ])->title($po->number);
    }
}
