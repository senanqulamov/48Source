<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Models\Category;
use App\Models\Rfq;
use App\Models\Sequence;
use App\Services\AssistantService;
use App\Services\AwardService;
use App\Services\QuoteEvaluator;
use App\Support\Audit;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Assistant')]
class Assistant extends Component
{
    use Page;

    public string $sentence = '';

    public ?array $draft = null;

    public string $review = '';

    public string $category = '';

    public function mount(): void
    {
        abort_unless($this->me()->isSourcing(), 403);
        $this->need('rfqs');
    }

    public function propose(AssistantService $assistant): void
    {
        if (mb_strlen(trim($this->sentence)) < 8) {
            $this->toast('Describe what you need in a sentence first.', 'alert');

            return;
        }
        $this->draft = $assistant->draftFromSentence($this->sentence);
    }

    /** Nothing exists until the user presses this: it saves a draft request, which they then finish and publish themselves. */
    public function createDraft()
    {
        $this->need('rfqs', 'C');
        if (! $this->draft || ! $this->draft['items']) {
            $this->toast('There are no items to put in a draft yet.', 'alert');

            return null;
        }
        $d = $this->draft;
        $rfq = Rfq::create(['number' => Sequence::next('RFQ'), 'title' => $d['title'], 'description' => trim($this->sentence), 'type' => 'invited', 'category_id' => $d['category_id'], 'buyer_id' => $this->me()->id, 'status' => 'draft', 'currency' => \App\Models\Currency::codes()[0], 'deadline_at' => now()->addDays($d['deadline_days'])->setTime(17, 0)]);
        foreach ($d['items'] as $i => $it) {
            $rfq->items()->create(['position' => $i + 1, 'name' => $it['name'], 'quantity_milli' => Money::parseQty($it['quantity']), 'unit' => $it['unit']]);
        }
        Audit::log('RFQ', 'Created draft request with the assistant', $rfq, $rfq->number);
        session()->flash('toast', "Draft {$rfq->number} created. Check it, choose suppliers, then publish.");

        return $this->redirectRoute('rfqs.edit', $rfq->number, navigate: true);
    }

    public function render(AssistantService $assistant, QuoteEvaluator $evaluator, AwardService $awards)
    {
        $u = $this->me();
        $rfq = $this->review !== '' && $u->allowed('quotes') ? Rfq::visibleTo($u)->where('number', $this->review)->first() : null;

        return view('livewire.assistant', [
            'withQuotes' => $u->allowed('quotes') ? Rfq::visibleTo($u)->whereIn('status', ['closed', 'awarded'])->whereHas('liveQuotes')->latest('id')->limit(40)->get(['id', 'number', 'title', 'sealed', 'status', 'deadline_at']) : collect(),
            'rfq' => $rfq, 'findings' => $rfq && ! $rfq->pricesHidden() ? $assistant->reviewQuotations($rfq, $evaluator, $awards) : [],
            'categories' => Category::options(), 'suggested' => $this->category !== '' && $u->allowed('suppliers') ? $assistant->suggestSuppliers((int) $this->category) : null,
        ]);
    }
}
