<?php

namespace App\Livewire\Requisitions;

use App\Livewire\Concerns\Page;
use App\Models\ActivityLog;
use App\Models\Requisition;
use App\Services\RequisitionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Requisition')]
class Show extends Component
{
    use Page;

    public string $number;

    public string $comment = '';

    public function mount(string $number): void
    {
        $this->number = $number;
        $this->req();
    }

    protected function req(): Requisition
    {
        return Requisition::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    public function submit(RequisitionService $service): void
    {
        $this->need('requisitions', 'C');
        $this->attempt(fn () => $service->submit($this->req(), $this->me()), 'Sent for approval.');
    }

    public function decide(RequisitionService $service, bool $approve): void
    {
        $this->need('requisitions', 'A');
        if ($this->attempt(fn () => $service->decide($this->req(), $this->me(), $approve, $this->comment), $approve ? 'Approved.' : 'Rejected. The requester was told why.')) {
            $this->reset('comment');
            $this->modal = null;
        }
    }

    public function convert(RequisitionService $service)
    {
        $this->need('rfqs', 'C');
        $rfq = null;
        if ($this->attempt(function () use ($service, &$rfq) {
            $rfq = $service->convert($this->req(), $this->me());
        })) {
            session()->flash('toast', "Draft request {$rfq->number} created. Add the deadline and suppliers, then publish.");

            return $this->redirectRoute('rfqs.edit', $rfq->number, navigate: true);
        }
    }

    public function cancel(RequisitionService $service): void
    {
        $this->need('requisitions', 'E');
        $this->attempt(fn () => $service->cancel($this->req(), $this->me()), 'Requisition cancelled.');
        $this->modal = null;
    }

    public function render(RequisitionService $service)
    {
        $req = $this->req()->load('items', 'requester', 'category', 'approvals.approver', 'rfq');

        return view('livewire.requisitions.show', ['req' => $req, 'canDecide' => $service->canDecide($req, $this->me()), 'mine' => $req->requester_id === $this->me()->id || $this->me()->isAdmin(),
            'logs' => ActivityLog::where('subject_type', 'requisition')->where('subject_id', $req->id)->latest('created_at')->get()])->title($req->number);
    }
}
