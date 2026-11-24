<?php

namespace App\Livewire\Rfqs;

use App\Exceptions\BusinessRule;
use App\Livewire\Concerns\Page;
use App\Models\FeatureFlag;
use App\Models\Rfq;
use App\Services\AwardService;
use App\Services\QuoteEvaluator;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Compare extends Component
{
    use Page;

    public string $number;

    public string $preset = 'balanced';

    public ?int $pick = null;

    public array $hidden = [];

    public string $focus = 'all';

    public string $justification = '';

    public array $bafo = ['deadline' => '', 'message' => ''];

    public array $auc = ['minutes' => '30', 'step' => '0.5'];

    public function openBafo(\App\Services\BafoService $service): void
    {
        $this->need('quotes', 'A');
        if ($this->attempt(fn () => $service->open($this->rfq(), $this->me(), $this->bafo['deadline'] ?: 'now', $this->bafo['message']), 'Best-and-final round opened. The suppliers were told.')) {
            $this->modal = null;
        }
    }

    public function closeBafo(\App\Services\BafoService $service): void
    {
        $this->need('quotes', 'A');
        $round = $this->rfq()->openBafo();
        if ($round) {
            $this->attempt(fn () => $service->close($round), 'Round closed. Quotations are final.');
        }
    }

    public function startAuction(\App\Services\AuctionService $service)
    {
        $this->need('quotes', 'A');
        if ($this->attempt(fn () => $service->open($this->rfq(), $this->me(), (int) $this->auc['minutes'], (int) round(((float) str_replace(',', '.', $this->auc['step'])) * 100)))) {
            session()->flash('toast', 'Auction started. The suppliers were told.');

            return $this->redirectRoute('rfqs.auction', $this->number, navigate: true);
        }
    }

    public function mount(string $number): void
    {
        abort_unless($this->me()->isSourcing(), 403);
        $this->number = $number;
        $this->preset = $this->rfq()->scoring_preset;
    }

    protected function rfq(): Rfq
    {
        return Rfq::visibleTo($this->me())->where('number', $this->number)->with('items')->firstOrFail();
    }

    public function choose(int $quoteId): void
    {
        $this->need('quotes', 'A');
        $this->pick = $quoteId;
        $this->justification = '';
        $this->open('award');
    }

    public function toggleSupplier(int $supplierId): void
    {
        $this->need('quotes', 'A');
        $ids = $this->rfq()->liveQuotes()->pluck('supplier_id')->unique()->values()->all();
        if (! in_array($supplierId, $ids, true)) return;
        if (in_array($supplierId, $this->hidden, true)) {
            $this->hidden = array_values(array_diff($this->hidden, [$supplierId]));
            return;
        }
        if (count($ids) - count($this->hidden) <= 1) return;
        $this->hidden = array_values(array_unique([...$this->hidden, $supplierId]));
        if ((string) $supplierId === $this->focus) $this->focus = 'all';
    }

    public function award(AwardService $awards)
    {
        $this->need('quotes', 'A');
        $rfq = $this->rfq();
        $quote = $rfq->liveQuotes()->findOrFail($this->pick);
        $po = null;
        if (! $this->attempt(function () use ($awards, $rfq, $quote, &$po) {
            $po = $awards->award($rfq, $quote, $this->me(), $this->justification);
        })) {
            return null;
        }
        session()->flash('toast', "Awarded. Draft purchase order {$po->number} created.");

        return $this->redirect(route('orders.show', $po), navigate: true);
    }

    public function splitAward(AwardService $awards)
    {
        $this->need('quotes', 'A');
        $orders = null;
        if (! $this->attempt(function () use ($awards, &$orders) {
            $orders = $awards->splitAward($this->rfq(), $this->me());
        })) {
            return null;
        }
        session()->flash('toast', 'Awarded item by item. '.$orders->count().' draft purchase order(s) created.');

        return $this->redirect(route('orders.index'), navigate: true);
    }

    public function render(QuoteEvaluator $evaluator, AwardService $awards)
    {
        $rfq = $this->rfq();
        abort_if($rfq->pricesHidden(), 403, 'Quotations on this request are sealed until quoting closes.');
        $rows = $evaluator->evaluate($rfq, $this->preset)
            ->reject(fn (array $row) => in_array($row['quote']->supplier_id, $this->hidden, true))
            ->values();
        $prices = [];
        $low = [];
        foreach ($rows as $r) {
            foreach ($r['quote']->items as $qi) {
                $prices[$r['quote']->id][$qi->rfq_item_id] = $qi;
                if ($qi->unit_price_cents !== null) {
                    $low[$qi->rfq_item_id] = min($low[$qi->rfq_item_id] ?? PHP_INT_MAX, $qi->unit_price_cents);
                }
            }
        }
        $plan = null;
        $planError = null;
        if ($rows->count() > 1 && $rfq->status === 'closed' && FeatureFlag::isOn('split_awards') && ! $awards->rfqBlockers($rfq)) {
            try {
                $plan = $awards->splitPlan($rfq);
            } catch (BusinessRule $e) {
                $planError = $e->getMessage();
            }
        }
        $lowest = $awards->lowestComplete($rfq);
        $picked = $this->pick ? $rows->firstWhere(fn ($r) => $r['quote']->id === $this->pick) : null;

        return view('livewire.rfqs.compare', [
            'round' => $rfq->openBafo(), 'rounds' => $rfq->bafoRounds()->count(), 'auction' => $rfq->auction,
            'rfq' => $rfq, 'rows' => $rows, 'prices' => $prices, 'low' => $low, 'blockers' => $awards->rfqBlockers($rfq),
            'lowest' => $lowest, 'plan' => $plan, 'planError' => $planError, 'picked' => $picked,
            'needsWhy' => $picked ? $awards->needsJustification($rfq, $picked['quote']) : false,
            'allSupplierIds' => $rfq->liveQuotes()->pluck('supplier_id')->unique()->values(),
        ])->title('Compare '.$rfq->number);
    }
}
