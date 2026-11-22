<?php

namespace App\Livewire\Procurement\Comparison;

use App\Models\Quote;
use App\Models\Request;
use App\Services\Procurement\AwardAndPurchaseOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Request $request;

    public ?int $selectedQuoteId = null;

    public string $justification = '';

    public bool $confirmingAward = false;

    public function mount(Request $request): void
    {
        $actor = auth()->user();
        abort_unless($actor && $actor->can('view_rfqs')
            && ($actor->isAdmin() || ($actor->isBuyer() && $request->buyer_id === $actor->id)), 403);

        $this->request = $request;
    }

    #[Computed]
    public function matrix(): array
    {
        $items = $this->request->items()->orderBy('id')->get();
        $quotes = Quote::query()->where('request_id', $this->request->id)
            ->whereIn('status', ['submitted', 'accepted'])
            ->with(['supplier:id,name', 'items'])
            ->orderBy('id')
            ->get();

        $lines = [];
        foreach ($items as $item) {
            /** @var \App\Models\RequestItem $item */
            $lines[$item->id] = [
                'id' => (int) $item->id,
                'name' => (string) ($item->product_name ?: 'Requested item'),
                'quantity' => (float) $item->quantity,
                'lowest' => [],
            ];
        }

        $suppliers = [];
        $currencies = [];
        foreach ($quotes as $quote) {
            $currency = strtoupper(trim((string) $quote->currency));
            $currencies[$currency] = true;
            $linePrices = [];
            $amount = 0;
            $coverage = 0;
            foreach ($quote->items as $quotedLine) {
                $id = (int) $quotedLine->request_item_id;
                if (! isset($lines[$id]) || array_key_exists($id, $linePrices)) {
                    continue;
                }
                $price = (float) $quotedLine->unit_price;
                $tax = (float) $quotedLine->tax_rate;
                if ($price <= 0 || $tax < 0 || $tax > 100) {
                    continue;
                }
                $lineTotal = round($price * $lines[$id]['quantity'] * (1 + $tax / 100), 2);
                $linePrices[$id] = ['price' => $price, 'tax' => $tax, 'total' => $lineTotal];
                $amount += $lineTotal;
                $coverage++;
                $previous = $lines[$id]['lowest'][$currency] ?? null;
                $lines[$id]['lowest'][$currency] = $previous === null ? $price : min($price, $previous);
            }
            $suppliers[] = [
                'id' => (int) $quote->id,
                'supplier' => data_get($quote, 'supplier.name', 'Supplier'),
                'currency' => $currency,
                'total' => round($amount, 2),
                'coverage' => $coverage,
                'complete' => $coverage === count($lines) && $coverage > 0,
                'lines' => $linePrices,
                'status' => $quote->status,
                'submitted_at' => $quote->submitted_at,
            ];
        }

        $singleCurrency = count($currencies) === 1;
        /** @var array<int,array<string,mixed>> $complete */
        $complete = array_values(array_filter($suppliers, static fn (array $quote): bool => $quote['complete']));
        usort($complete, static fn (array $first, array $second): int => $first['total'] <=> $second['total']);
        return [
            'items' => array_values($lines),
            'quotes' => $suppliers,
            'currency_comparable' => $singleCurrency,
            'lowest_complete' => $singleCurrency ? ($complete[0] ?? null) : null,
            'currencies' => array_keys($currencies),
        ];
    }

    public function selectForAward(int $id): void
    {
        abort_unless($this->request->status === 'closed', 403);
        if (! $this->matrix()['currency_comparable']) {
            throw ValidationException::withMessages(['award' => 'Normalize currencies before selecting a quotation.']);
        }
        $selection = collect($this->matrix()['quotes'])->firstWhere('id', $id);
        if (! $selection || ! $selection['complete']) {
            throw ValidationException::withMessages(['award' => 'Only a quotation covering all requested items can be awarded.']);
        }

        $quote = Quote::query()->where('request_id', $this->request->id)
            ->where('status', 'submitted')->findOrFail($id);

        $this->selectedQuoteId = (int) $quote->id;
        $this->justification = '';
        $this->confirmingAward = true;
    }

    public function award(AwardAndPurchaseOrderService $service): void
    {
        $actor = auth()->user();
        abort_unless($actor && ($actor->isAdmin() || ($actor->isBuyer()
            && $this->request->buyer_id === $actor->id)), 403);
        abort_unless($this->confirmingAward && $this->selectedQuoteId !== null, 403);

        $order = $service->award($this->request, $this->selectedQuoteId, $actor, $this->justification);
        $this->confirmingAward = false;
        $this->redirectRoute('procurement.orders.show', ['purchaseOrder' => $order->id], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.procurement.comparison.show')
            ->layout('layouts.app');
    }
}
