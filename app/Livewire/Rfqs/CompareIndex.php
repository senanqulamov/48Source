<?php

namespace App\Livewire\Rfqs;

use App\Livewire\Concerns\Page;
use App\Models\Rfq;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Compare quotations')]
class CompareIndex extends Component
{
    use Page;

    public function render()
    {
        abort_unless($this->me()->isSourcing(), 403);

        return view('livewire.rfqs.compare-index', [
            'ready' => Rfq::visibleTo($this->me())->where('status', 'closed')->whereHas('liveQuotes')->withCount('liveQuotes')->orderBy('closed_at')->get(),
            'open' => Rfq::visibleTo($this->me())->where('status', 'open')->whereHas('liveQuotes')->withCount('liveQuotes')->orderBy('deadline_at')->get(),
            'done' => Rfq::visibleTo($this->me())->where('status', 'awarded')->withCount('liveQuotes')->latest('awarded_at')->limit(10)->get(),
        ]);
    }
}
