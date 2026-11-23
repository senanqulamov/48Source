<?php

namespace App\Livewire\Quotes;

use App\Livewire\Concerns\Page;
use App\Models\Quote;
use App\Models\Rfq;
use App\Services\QuoteService;
use App\Support\Money;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    use Page, \Livewire\WithFileUploads;

    public $upload = null;

    public string $number;

    public array $prices = [];

    public string $currency = 'USD';

    public string $delivery_days = '';

    public string $payment_terms = '';

    public string $valid_until = '';

    public string $notes = '';

    public function mount(string $number): void
    {
        abort_unless($this->me()->role === 'supplier' && $this->me()->supplier_id, 403);
        $this->number = $number;
        $rfq = $this->rfq();
        $q = $this->quote($rfq);
        $this->currency = $q->currency ?? $rfq->currency;
        $this->delivery_days = (string) ($q->delivery_days ?? '');
        $this->payment_terms = (string) ($q->payment_terms ?? $rfq->payment_terms);
        $this->valid_until = $q?->valid_until?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d');
        $this->notes = (string) ($q->notes ?? '');
        $have = $q ? $q->items->keyBy('rfq_item_id') : collect();
        foreach ($rfq->items as $item) {
            $this->prices[$item->id] = Money::input($have[$item->id]->unit_price_cents ?? null);
        }
    }

    protected function rfq(): Rfq
    {
        return Rfq::visibleTo($this->me())->where('number', $this->number)->with('items', 'buyer', 'category')->firstOrFail();
    }

    protected function quote(Rfq $rfq): ?Quote
    {
        return Quote::where('rfq_id', $rfq->id)->where('supplier_id', $this->me()->supplier_id)->with('items')->first();
    }

    public function submit(QuoteService $quotes)
    {
        $rfq = $this->rfq();
        $existing = $this->quote($rfq);
        $this->need('quotes', $existing && $existing->status === 'submitted' ? 'E' : 'C');
        if (! $this->attempt(fn () => $quotes->submit($rfq, $this->me()->supplier, $this->me(), ['currency' => $this->currency, 'delivery_days' => $this->delivery_days, 'payment_terms' => $this->payment_terms, 'valid_until' => $this->valid_until, 'notes' => $this->notes, 'prices' => $this->prices]))) {
            return null;
        }
        session()->flash('toast', "Quotation on {$rfq->number} ".($existing && $existing->status === 'submitted' ? 'revised.' : 'submitted.'));

        return $this->redirect(route('quotes.invitations', ['tab' => 'sent']), navigate: true);
    }

    public function updatedUpload(\App\Services\AttachmentService $files): void
    {
        $this->need('quotes', 'E');
        $this->validate(['upload' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,xls,docx,doc,csv,txt,zip'], ['upload.max' => 'The file is larger than 10 MB.', 'upload.mimes' => 'This kind of file is not accepted. Use PDF, Office, image, CSV or ZIP files.']);
        $rfq = $this->rfq();
        $quote = $this->quote($rfq);
        if (! $quote || $quote->status === 'draft') {
            $this->toast('Send your quotation first, then attach files to it.', 'alert');
        } else {
            $this->attempt(fn () => $files->addToQuote($quote, $this->me(), $this->upload), 'File attached to your quotation.');
        }
        $this->reset('upload');
    }

    public function removeFile(\App\Services\AttachmentService $files, int $id): void
    {
        $quote = $this->quote($this->rfq());
        $this->attempt(fn () => $files->remove($quote->attachments()->findOrFail($id), $this->me()), 'File removed.');
    }

    public string $question = '';

    public function ask(\App\Services\QaService $qa): void
    {
        $this->need('quotes', 'E');
        if ($this->attempt(fn () => $qa->ask($this->rfq(), $this->me(), $this->question), 'Question sent to the buyer.')) {
            $this->question = '';
        }
    }

    public function render()
    {
        $rfq = $this->rfq();
        $quote = $this->quote($rfq);
        $total = 0;
        $priced = 0;
        $lines = [];
        foreach ($rfq->items as $item) {
            try {
                $p = Money::parse($this->prices[$item->id] ?? null);
            } catch (InvalidArgumentException) {
                $p = null;
            }
            $lines[$item->id] = $p !== null && $p > 0 ? Money::lineTotal($p, $item->quantity_milli) : null;
            if ($lines[$item->id] !== null) {
                $total += $lines[$item->id];
                $priced++;
            }
        }
        $supplier = $this->me()->supplier;
        $inv = $rfq->invitations()->where('supplier_id', $supplier->id)->first();
        $bafo = $rfq->status === 'closed' && $quote?->status === 'submitted' ? $rfq->openBafo() : null;
        $editable = ($rfq->isOpenForQuotes() || $bafo) && $supplier->isActive() && $inv?->status !== 'declined' && (! $quote || in_array($quote->status, ['draft', 'submitted', 'withdrawn'], true));

        return view('livewire.quotes.form', compact('rfq', 'quote', 'total', 'priced', 'lines', 'supplier', 'inv', 'editable', 'bafo') + ['qa' => $rfq->questions()->where(fn ($q) => $q->whereNotNull('answer')->orWhere('supplier_id', $supplier->id))->latest('id')->get(), 'auction' => $rfq->auction, 'rfqFiles' => $rfq->attachments()->latest('id')->get(), 'myFiles' => $quote ? $quote->attachments()->latest('id')->get() : collect()])->title('Quote '.$rfq->number);
    }
}
