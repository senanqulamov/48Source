<?php

namespace App\Livewire\Rfqs;

use App\Livewire\Concerns\Page;
use App\Models\Category;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Services\QuoteEvaluator;
use App\Services\QuoteService;
use App\Services\RfqService;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Request for quotation')]
class Form extends Component
{
    use Page;

    public ?string $number = null;

    public int $step = 1;

    public array $f = ['title' => '', 'description' => '', 'type' => 'invited', 'category_id' => '', 'currency' => 'USD', 'budget' => '', 'deadline_at' => '', 'delivery_location' => '', 'required_by' => '', 'requirements' => '', 'payment_terms' => 'Net 30', 'scoring_preset' => 'balanced', 'sealed' => false];

    public array $items = [];

    public array $suppliers = [];

    public string $find = '';

    public function mount(?string $number = null): void
    {
        abort_unless($this->me()->isSourcing(), 403);
        if ($number === null) {
            $this->f['deadline_at'] = now()->addDays(10)->format('Y-m-d\T17:00');
            $this->items = [$this->blank()];
            if ($prefill = session()->pull('assistant.rfq_prefill')) {
                $this->f = array_replace($this->f, $prefill['f'] ?? []);
                $this->items = $prefill['items'] ?? $this->items;
            }

            return;
        }
        $rfq = Rfq::visibleTo($this->me())->where('number', $number)->with('items', 'invitations')->firstOrFail();
        abort_unless($rfq->status === 'draft', 403, 'Only a draft request can be edited.');
        $this->number = $rfq->number;
        $this->f = ['title' => $rfq->title, 'description' => (string) $rfq->description, 'type' => $rfq->type, 'category_id' => (string) $rfq->category_id, 'currency' => $rfq->currency, 'budget' => Money::input($rfq->budget_cents), 'deadline_at' => $rfq->deadline_at?->format('Y-m-d\TH:i') ?? '', 'delivery_location' => (string) $rfq->delivery_location, 'required_by' => $rfq->required_by?->format('Y-m-d') ?? '', 'requirements' => (string) $rfq->requirements, 'payment_terms' => (string) $rfq->payment_terms, 'scoring_preset' => $rfq->scoring_preset, 'sealed' => $rfq->sealed];
        $this->items = $rfq->items->map(fn ($i) => ['name' => $i->name, 'quantity' => Money::qtyInput($i->quantity_milli), 'unit' => $i->unit, 'target_price' => Money::input($i->target_price_cents)])->all() ?: [$this->blank()];
        $this->suppliers = $rfq->invitations->pluck('supplier_id')->map(fn ($id) => (string) $id)->all();
    }

    private function blank(): array
    {
        return ['name' => '', 'quantity' => '', 'unit' => 'pcs', 'target_price' => ''];
    }

    public function addItem(): void
    {
        $this->items[] = $this->blank();
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items) ?: [$this->blank()];
    }

    protected function rulesFor(int $step): array
    {
        return match ($step) {
            1 => ['f.title' => 'required|string|min:4|max:160', 'f.type' => 'required|in:invited,public', 'f.category_id' => 'nullable|exists:categories,id', 'f.currency' => 'required|in:'.implode(',', \App\Models\Currency::codes()),
                'f.budget' => ['nullable', 'regex:/^\d{1,12}([.,]\d{1,2})?$/'], 'f.deadline_at' => 'required|date|after:now', 'f.required_by' => 'nullable|date|after_or_equal:today', 'f.delivery_location' => 'nullable|string|max:190', 'f.payment_terms' => 'nullable|string|max:60', 'f.scoring_preset' => 'required|in:'.implode(',', array_keys(QuoteEvaluator::PRESETS))],
            2 => ['items' => 'required|array|min:1', 'items.*.name' => 'required|string|min:2|max:190', 'items.*.quantity' => ['required', 'regex:/^\d{1,9}([.,]\d{1,3})?$/', 'not_in:0,0.0,0.00,0.000'], 'items.*.unit' => 'required|string|max:20', 'items.*.target_price' => ['nullable', 'regex:/^\d{1,12}([.,]\d{1,2})?$/']],
            3 => $this->f['type'] === 'invited' ? ['suppliers' => 'required|array|min:1'] : ['suppliers' => 'array'],
            default => [],
        };
    }

    protected function validationAttributes(): array
    {
        return ['f.title' => 'title', 'f.deadline_at' => 'quotation deadline', 'f.budget' => 'budget', 'f.required_by' => 'delivery date', 'items.*.name' => 'item name', 'items.*.quantity' => 'quantity', 'items.*.unit' => 'unit', 'items.*.target_price' => 'target price', 'suppliers' => 'suppliers'];
    }

    protected function messages(): array
    {
        return ['suppliers.required' => 'Choose at least one supplier to invite.', 'suppliers.min' => 'Choose at least one supplier to invite.', 'items.*.quantity.not_in' => 'The quantity must be above zero.'];
    }

    public function go(int $step): void
    {
        for ($s = 1; $s < $step; $s++) {
            $this->validate($this->rulesFor($s));
        }
        $this->step = max(1, min(4, $step));
    }

    private function persist(RfqService $service): Rfq
    {
        $rfq = $this->number ? Rfq::visibleTo($this->me())->where('number', $this->number)->firstOrFail() : null;
        $this->need('rfqs', $rfq ? 'E' : 'C');

        return $service->save($rfq?->buyer ?? $this->me(), $this->f + ['items' => $this->items, 'suppliers' => array_map('intval', $this->suppliers)], $rfq);
    }

    public function saveDraft(RfqService $service)
    {
        $this->validate(['f.title' => 'required|string|min:4|max:160'] + array_filter($this->rulesFor(1), fn ($k) => in_array($k, ['f.budget', 'f.category_id', 'f.currency'], true), ARRAY_FILTER_USE_KEY));
        $rfq = null;
        if (! $this->attempt(function () use ($service, &$rfq) {
            $rfq = $this->persist($service);
        })) {
            return null;
        }
        session()->flash('toast', "Draft {$rfq->number} saved.");

        return $this->redirect(route('rfqs.show', $rfq), navigate: true);
    }

    public function publish(RfqService $service)
    {
        foreach ([1, 2, 3] as $s) {
            $this->validate($this->rulesFor($s));
        }
        $rfq = null;
        if (! $this->attempt(function () use ($service, &$rfq) {
            $rfq = $service->publish($this->persist($service));
        })) {
            return null;
        }
        session()->flash('toast', "{$rfq->number} published. Invited suppliers have been notified.");

        return $this->redirect(route('rfqs.show', $rfq), navigate: true);
    }

    public function render()
    {
        $list = Supplier::where('status', 'active')->with('category')
            ->when($this->find !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->find}%")->orWhere('city', 'like', "%{$this->find}%")))
            ->orderByRaw('case when category_id = ? then 0 else 1 end', [(int) $this->f['category_id']])->orderBy('name')->get();

        return view('livewire.rfqs.form', ['categories' => Category::options(), 'supplierList' => $list, 'chosen' => Supplier::whereIn('id', $this->suppliers)->orderBy('name')->get()]);
    }
}
