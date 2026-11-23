<?php

namespace App\Livewire\Rfis;

use App\Livewire\Concerns\Page;
use App\Models\Rfi;
use App\Services\RfiService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Information request')]
class Show extends Component
{
    use Page;

    public string $number;

    public array $answers = [];

    public function mount(string $number): void
    {
        $this->number = $number;
        $rfi = $this->rfi();
        if ($this->me()->role === 'supplier') {
            $mine = $rfi->responses()->where('supplier_id', $this->me()->supplier_id)->first();
            foreach ($rfi->questions as $i => $_) {
                $this->answers[$i] = $mine?->answers[$i] ?? '';
            }
        }
    }

    protected function rfi(): Rfi
    {
        return Rfi::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    public function publish(RfiService $service): void
    {
        $this->need('rfqs', 'E');
        $this->attempt(fn () => $service->publish($this->rfi()->load('responses')), 'Sent to the suppliers.');
    }

    public function closeNow(RfiService $service): void
    {
        $this->need('rfqs', 'E');
        $this->attempt(fn () => $service->close($this->rfi()), 'Closed. Suppliers can no longer answer.');
    }

    public function answer(RfiService $service): void
    {
        $this->need('quotes', 'E');
        $this->attempt(fn () => $service->answer($this->rfi(), $this->me(), $this->answers), 'Your answers were sent to the buyer.');
    }

    public function toRfq(RfiService $service)
    {
        $this->need('rfqs', 'C');
        $rfq = null;
        if ($this->attempt(function () use ($service, &$rfq) {
            $rfq = $service->toRfq($this->rfi(), $this->me());
        })) {
            session()->flash('toast', "Draft request {$rfq->number} created with the suppliers that answered.");

            return $this->redirectRoute('rfqs.edit', $rfq->number, navigate: true);
        }
    }

    public function render()
    {
        $u = $this->me();
        $rfi = $this->rfi()->load('buyer', 'rfq', 'responses.supplier');

        return view('livewire.rfis.show', ['rfi' => $rfi, 'sourcing' => $u->isSourcing(), 'mine' => $u->role === 'supplier' ? $rfi->responses->firstWhere('supplier_id', $u->supplier_id) : null])->title($rfi->number);
    }
}
