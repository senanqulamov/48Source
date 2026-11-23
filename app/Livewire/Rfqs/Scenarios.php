<?php

namespace App\Livewire\Rfqs;

use App\Exceptions\BusinessRule;
use App\Livewire\Concerns\Page;
use App\Models\FeatureFlag;
use App\Models\Rfq;
use App\Services\AwardService;
use App\Services\QuoteEvaluator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Award scenarios')]
class Scenarios extends Component
{
    use Page;

    public string $number;

    public function mount(string $number): void
    {
        $this->number = $number;
        abort_unless($this->me()->isSourcing(), 403);
        $this->need('quotes');
        abort_if($this->rfq()->pricesHidden(), 403, 'Quotations on this request are sealed until quoting closes.');
    }

    protected function rfq(): Rfq
    {
        return Rfq::visibleTo($this->me())->where('number', $this->number)->whereIn('status', ['open', 'closed', 'awarded'])->firstOrFail();
    }

    public function render(QuoteEvaluator $evaluator, AwardService $awards)
    {
        $rfq = $this->rfq()->load('items');
        $rows = $evaluator->evaluate($rfq);
        $lowest = $awards->lowestComplete($rfq);
        $best = $rows->filter(fn ($r) => $r['complete'] && ! $r['quote']->isExpired())->sortBy('rank')->first();
        $plan = null;
        $planError = null;
        if (FeatureFlag::isOn('split_awards') && $rows->count() > 1) {
            try {
                $plan = $awards->splitPlan($rfq);
            } catch (BusinessRule $e) {
                $planError = $e->getMessage();
            }
        }
        $scenarios = collect([
            $lowest ? ['key' => 'lowest', 'name' => 'Lowest single supplier', 'who' => $lowest->supplier->name, 'total' => $lowest->total_cents, 'orders' => 1, 'days' => $lowest->delivery_days, 'note' => 'One order, the lowest complete price. No written reason needed.'] : null,
            $best ? ['key' => 'best', 'name' => 'Best overall score', 'who' => $best['quote']->supplier->name, 'total' => $best['quote']->total_cents, 'orders' => 1, 'days' => $best['quote']->delivery_days, 'note' => $lowest && $lowest->id === $best['quote']->id ? 'The same supplier as the lowest price.' : 'Scores highest on price, quality, delivery and terms together. Needs a written reason because it is not the lowest price.'] : null,
            $plan ? ['key' => 'split', 'name' => 'Cheapest supplier for each item', 'who' => $plan->count() . ' suppliers', 'total' => (int) $plan->sum('total'), 'orders' => $plan->count(), 'days' => (int) $plan->max(fn ($p) => $p['quote']->delivery_days), 'note' => 'The lowest total, but ' . $plan->count() . ' orders to follow and the slowest supplier sets the date.'] : null,
        ])->filter()->values();
        $min = $scenarios->min('total');

        return view('livewire.rfqs.scenarios', ['rfq' => $rfq, 'scenarios' => $scenarios, 'min' => $min, 'plan' => $plan, 'planError' => $planError, 'lowest' => $lowest,
            'cur' => $lowest?->currency ?? $rfq->currency])->title('Scenarios ' . $rfq->number);
    }
}
