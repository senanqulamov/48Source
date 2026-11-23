<?php

namespace App\Livewire\Invoices;

use App\Livewire\Concerns\Page;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\ReceiptService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Invoice')]
class Show extends Component
{
    use Page;

    public string $number;

    public string $note = '';

    public function mount(string $number): void
    {
        $this->number = $number;
        $this->invoice();
    }

    protected function invoice(): Invoice
    {
        return Invoice::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    public function rematch(InvoiceService $service): void
    {
        $this->need('invoices', 'E');
        $this->attempt(function () use ($service) {
            $inv = $service->rematch($this->invoice());
            $this->toast($inv->status === 'matched' ? 'The invoice now matches the order and receipts.' : 'Still ' . count($inv->issues) . ' difference(s).', $inv->status === 'matched' ? 'check' : 'alert');
        });
    }

    public function approve(InvoiceService $service): void
    {
        $this->need('invoices', 'A');
        if ($this->attempt(fn () => $service->approve($this->invoice(), $this->me(), $this->note), 'Approved for payment.')) {
            $this->reset('note');
            $this->modal = null;
        }
    }

    public function reject(InvoiceService $service): void
    {
        $this->need('invoices', 'A');
        if ($this->attempt(fn () => $service->reject($this->invoice(), $this->me(), $this->note), 'Invoice rejected. The supplier was told why.')) {
            $this->reset('note');
            $this->modal = null;
        }
    }

    public function pay(InvoiceService $service): void
    {
        $this->need('invoices', 'A');
        $this->attempt(fn () => $service->markPaid($this->invoice(), $this->me()), 'Marked as paid.');
    }

    public function render(ReceiptService $receipts, InvoiceService $service)
    {
        $inv = $this->invoice()->load('items', 'supplier', 'order.items', 'order.buyer', 'submitter', 'decider');

        return view('livewire.invoices.show', ['inv' => $inv, 'po' => $inv->order, 'received' => $receipts->received($inv->order), 'before' => $service->invoiced($inv->order, $inv->id),
            'buyerSide' => $this->me()->isSourcing(), 'lines' => $inv->items->keyBy('purchase_order_item_id')])->title($inv->number);
    }
}
