<?php

namespace App\Livewire\Contracts;

use App\Livewire\Concerns\Page;
use App\Models\Contract;
use App\Models\Supplier;
use App\Services\ContractService;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Contract')]
class Form extends Component
{
    use Page;

    public ?string $number = null;

    public array $f = ['supplier_id' => '', 'title' => '', 'currency' => 'USD', 'ceiling' => '', 'starts_on' => '', 'ends_on' => '', 'payment_terms' => 'Net 30', 'terms' => ''];

    public array $items = [['name' => '', 'unit' => 'pcs', 'price' => '']];

    public function mount(?string $number = null): void
    {
        $this->need('contracts', $number ? 'E' : 'C');
        abort_unless($this->me()->isSourcing(), 403);
        $this->f['starts_on'] = now()->toDateString();
        $this->f['ends_on'] = now()->addYear()->toDateString();
        if ($number) {
            $c = Contract::visibleTo($this->me())->where('number', $number)->where('status', 'draft')->firstOrFail();
            $this->number = $number;
            $this->f = ['supplier_id' => (string) $c->supplier_id, 'title' => $c->title, 'currency' => $c->currency, 'ceiling' => Money::input($c->ceiling_cents), 'starts_on' => $c->starts_on->format('Y-m-d'), 'ends_on' => $c->ends_on->format('Y-m-d'), 'payment_terms' => (string) $c->payment_terms, 'terms' => (string) $c->terms];
            $this->items = $c->items->map(fn ($i) => ['name' => $i->name, 'unit' => $i->unit, 'price' => Money::input($i->unit_price_cents)])->all() ?: $this->items;
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['name' => '', 'unit' => 'pcs', 'price' => ''];
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items) ?: [['name' => '', 'unit' => 'pcs', 'price' => '']];
    }

    public function save(ContractService $service)
    {
        $this->need('contracts', $this->number ? 'E' : 'C');
        $c = null;
        if ($this->attempt(function () use ($service, &$c) {
            $existing = $this->number ? Contract::visibleTo($this->me())->where('number', $this->number)->firstOrFail() : null;
            $c = $service->save($this->me(), $this->f, array_values(array_filter($this->items, fn ($r) => trim($r['name'] ?? '') !== '' || trim($r['price'] ?? '') !== '')), $existing);
        })) {
            session()->flash('toast', "{$c->number} saved as a draft.");

            return $this->redirectRoute('contracts.show', $c->number, navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.contracts.form', ['suppliers' => Supplier::where('status', 'active')->orderBy('name')->pluck('name', 'id')]);
    }
}
