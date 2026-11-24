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

    /** Nothing exists until the user reviews and saves it in the RFQ wizard. */
    public function openWizard()
    {
        if (! $this->draft || ! $this->draft['items']) {
            $this->toast('There are no items to put in the RFQ wizard yet.', 'alert');

            return null;
        }
        $d = $this->draft;
        session()->put('assistant.rfq_prefill', ['f' => ['title' => $d['title'], 'description' => trim($this->sentence), 'category_id' => (string) ($d['category_id'] ?? ''), 'deadline_at' => now()->addDays($d['deadline_days'])->format('Y-m-d\T17:00')], 'items' => array_map(fn ($item) => $item + ['target_price' => ''], $d['items'])]);
        return $this->redirectRoute('rfqs.create', navigate: true);
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
