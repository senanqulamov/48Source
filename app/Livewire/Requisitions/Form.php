<?php

namespace App\Livewire\Requisitions;

use App\Livewire\Concerns\Page;
use App\Models\Category;
use App\Models\Requisition;
use App\Services\RequisitionService;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Requisition')]
class Form extends Component
{
    use Page;

    public ?string $number = null;

    public array $f = ['title' => '', 'justification' => '', 'needed_by' => '', 'category_id' => '', 'currency' => 'USD'];

    public array $items = [['name' => '', 'quantity' => '', 'unit' => 'pcs', 'est_price' => '']];

    public function mount(?string $number = null): void
    {
        $this->need('requisitions', $number ? 'E' : 'C');
        if ($number) {
            $r = Requisition::visibleTo($this->me())->where('number', $number)->where('status', 'draft')->firstOrFail();
            abort_unless($r->requester_id === $this->me()->id || $this->me()->isAdmin(), 403);
            $this->number = $number;
            $this->f = ['title' => $r->title, 'justification' => (string) $r->justification, 'needed_by' => $r->needed_by?->format('Y-m-d') ?? '', 'category_id' => (string) $r->category_id, 'currency' => $r->currency];
            $this->items = $r->items->map(fn ($i) => ['name' => $i->name, 'quantity' => Money::qtyInput($i->quantity_milli), 'unit' => $i->unit, 'est_price' => Money::input($i->est_price_cents)])->all() ?: $this->items;
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['name' => '', 'quantity' => '', 'unit' => 'pcs', 'est_price' => ''];
    }

    public function removeItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items) ?: [['name' => '', 'quantity' => '', 'unit' => 'pcs', 'est_price' => '']];
    }

    public function save(RequisitionService $service, bool $submit = false)
    {
        $this->need('requisitions', $this->number ? 'E' : 'C');
        $req = null;
        $ok = $this->attempt(function () use ($service, $submit, &$req) {
            $existing = $this->number ? Requisition::visibleTo($this->me())->where('number', $this->number)->firstOrFail() : null;
            $rows = array_values(array_filter($this->items, fn ($r) => trim($r['name'] ?? '') !== '' || trim($r['quantity'] ?? '') !== ''));
            $req = $service->save($this->me(), $this->f, $rows, $existing);
            $this->number = $req->number;
            if ($submit) {
                $service->submit($req, $this->me());
            }
        });
        if ($ok) {
            session()->flash('toast', $submit ? "{$req->number} sent for approval." : "{$req->number} saved as a draft.");

            return $this->redirectRoute('requisitions.show', $req->number, navigate: true);
        }
    }

    public function render(RequisitionService $service)
    {
        $total = 0;
        foreach ($this->items as $row) {
            try {
                $q = Money::parseQty($row['quantity'] ?? null);
                $p = Money::parse($row['est_price'] ?? null);
                $total += $q && $p ? Money::lineTotal($p, $q) : 0;
            } catch (\InvalidArgumentException) {
            }
        }

        return view('livewire.requisitions.form', ['categories' => Category::options(), 'total' => $total, 'chain' => $service->chainFor($total)]);
    }
}
