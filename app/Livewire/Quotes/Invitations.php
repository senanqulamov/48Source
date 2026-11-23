<?php

namespace App\Livewire\Quotes;

use App\Livewire\Concerns\Page;
use App\Models\Quote;
use App\Models\Rfq;
use App\Services\QuoteService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Invitations & quotations')]
class Invitations extends Component
{
    use Page;

    #[Url(except: 'open')]
    public string $tab = 'open';

    public ?string $target = null;

    public string $reason = '';

    public function mount(): void
    {
        abort_unless($this->me()->role === 'supplier' && $this->me()->supplier_id, 403);
    }

    private function rfq(string $number): Rfq
    {
        return Rfq::visibleTo($this->me())->where('number', $number)->firstOrFail();
    }

    public function accept(string $number, QuoteService $quotes): void
    {
        $this->need('quotes', 'E');
        $this->attempt(fn () => $quotes->respond($this->rfq($number), $this->me()->supplier, true), 'Invitation accepted. You can prepare your quotation now.');
    }

    public function askDecline(string $number): void
    {
        $this->target = $number;
        $this->reason = '';
        $this->open('decline');
    }

    public function decline(QuoteService $quotes): void
    {
        $this->need('quotes', 'E');
        if ($this->attempt(fn () => $quotes->respond($this->rfq($this->target), $this->me()->supplier, false, $this->reason), 'Invitation declined. The buyer has been told.')) {
            $this->close();
        }
    }

    public function withdraw(string $number, QuoteService $quotes): void
    {
        $this->need('quotes', 'E');
        $quote = Quote::where('rfq_id', $this->rfq($number)->id)->where('supplier_id', $this->me()->supplier_id)->firstOrFail();
        $this->attempt(fn () => $quotes->withdraw($quote), 'Quotation withdrawn. You can quote again until the deadline.');
    }

    /** One state per request, from this supplier's point of view. */
    public static function state(Rfq $r): string
    {
        $q = $r->quotes->first();
        $inv = $r->invitations->first();
        if ($q && in_array($q->status, ['won', 'lost'], true)) {
            return $q->status;
        }
        if ($q && $q->status === 'submitted') {
            return $r->status === 'open' ? 'sent' : ($r->status === 'cancelled' ? 'cancelled' : 'review');
        }
        if ($r->status !== 'open') {
            return $r->status === 'cancelled' ? 'cancelled' : 'missed';
        }
        if ($inv?->status === 'declined') {
            return 'declined';
        }
        if ($q && $q->status === 'withdrawn') {
            return 'withdrawn';
        }

        return $inv?->status === 'accepted' ? 'accepted' : ($inv ? 'pending' : 'public');
    }

    public function render()
    {
        $sid = $this->me()->supplier_id;
        $all = Rfq::visibleTo($this->me())->with(['buyer', 'items', 'invitations' => fn ($q) => $q->where('supplier_id', $sid), 'quotes' => fn ($q) => $q->where('supplier_id', $sid)])->orderByDesc('deadline_at')->get()
            ->each(fn (Rfq $r) => $r->setAttribute('my_state', self::state($r)));
        $groups = [
            'open' => $all->filter(fn ($r) => in_array($r->my_state, ['pending', 'accepted', 'public', 'withdrawn'], true))->sortBy('deadline_at'),
            'sent' => $all->filter(fn ($r) => in_array($r->my_state, ['sent', 'review', 'won'], true)),
            'closed' => $all->filter(fn ($r) => in_array($r->my_state, ['lost', 'declined', 'missed', 'cancelled'], true)),
        ];

        return view('livewire.quotes.invitations', ['groups' => $groups, 'rows' => $groups[$this->tab] ?? $groups['open'], 'supplier' => $this->me()->supplier]);
    }
}
