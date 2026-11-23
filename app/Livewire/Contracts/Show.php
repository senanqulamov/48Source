<?php

namespace App\Livewire\Contracts;

use App\Livewire\Concerns\Page;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\ContractRelease;
use App\Services\ContractService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Contract')]
class Show extends Component
{
    use Page;

    public string $number;

    #[\Livewire\Attributes\Url(except: 'prices')]
    public string $tab = 'prices';

    public array $qty = [];

    public string $neededBy = '';

    public string $note = '';

    public string $reason = '';

    public function mount(string $number): void
    {
        $this->number = $number;
        $this->contract();
    }

    protected function contract(): Contract
    {
        return Contract::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    public function activate(ContractService $service): void
    {
        $this->need('contracts', 'E');
        abort_unless($this->me()->isSourcing(), 403);
        $this->attempt(fn () => $service->activate($this->contract()), 'Contract is active. The supplier was told.');
    }

    public function terminate(ContractService $service): void
    {
        $this->need('contracts', 'E');
        abort_unless($this->me()->isSourcing(), 403);
        if ($this->attempt(fn () => $service->terminate($this->contract(), $this->reason), 'Contract terminated.')) {
            $this->reset('reason');
            $this->modal = null;
        }
    }

    public function release(ContractService $service): void
    {
        $this->need('contracts', 'C');
        abort_unless($this->me()->isSourcing(), 403);
        $ro = null;
        if ($this->attempt(function () use ($service, &$ro) {
            $ro = $service->release($this->contract()->load('items'), $this->me(), $this->qty, $this->neededBy, $this->note);
        })) {
            $this->toast("Release order {$ro->number} sent to the supplier.");
            $this->reset('qty', 'neededBy', 'note');
            $this->modal = null;
            $this->tab = 'releases';
        }
    }

    public function move(ContractService $service, int $id, string $to): void
    {
        $this->need('contracts', 'E');
        $ro = ContractRelease::where('contract_id', $this->contract()->id)->findOrFail($id);
        $this->attempt(fn () => $service->moveRelease($ro, $this->me(), $to), "{$ro->number} updated.");
    }

    public function render()
    {
        $c = $this->contract()->load('items', 'supplier', 'owner', 'releases.items', 'releases.creator');

        return view('livewire.contracts.show', ['c' => $c, 'sourcing' => $this->me()->isSourcing(),
            'logs' => $this->tab === 'history' ? ActivityLog::where('subject_type', 'contract')->where('subject_id', $c->id)->latest('created_at')->get() : collect()])->title($c->number);
    }
}
