<?php

namespace App\Livewire\Rfqs;

use App\Livewire\Concerns\Page;
use App\Models\ActivityLog;
use App\Models\Rfq;
use App\Services\RfqService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use Page, \Livewire\WithFileUploads;

    public $upload = null;

    public array $tool = ['deadline' => '', 'reason' => '', 'buyer' => '', 'quote' => '', 'target' => ''];

    public string $number;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    public string $reason = '';

    /** Answers being typed, by question id. */
    public array $reply = [];

    public array $fa = ['supplier_id' => '', 'title' => 'Site access and unloading survey', 'due_on' => ''];

    public function updatedUpload(\App\Services\AttachmentService $files): void
    {
        $this->need('rfqs', 'E');
        $this->validate(['upload' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,xls,docx,doc,csv,txt,dwg,zip'], ['upload.max' => 'The file is larger than 10 MB.', 'upload.mimes' => 'This kind of file is not accepted. Use PDF, Office, image, CSV, DWG or ZIP files.']);
        $this->attempt(fn () => $files->addToRfq($this->rfq(), $this->me(), $this->upload), 'File added. Invited suppliers can download it.');
        $this->reset('upload');
    }

    public function removeFile(\App\Services\AttachmentService $files, int $id): void
    {
        $this->need('rfqs', 'E');
        $this->attempt(fn () => $files->remove($this->rfq()->attachments()->findOrFail($id), $this->me()), 'File removed.');
    }

    public function remind(\App\Services\RfqToolsService $tools): void
    {
        $this->need('rfqs', 'E');
        $this->attempt(function () use ($tools) {
            $n = $tools->remind($this->rfq(), $this->me());
            $this->toast("Reminder sent to {$n} supplier(s).");
        });
    }

    public function duplicate(\App\Services\RfqToolsService $tools)
    {
        $this->need('rfqs', 'C');
        $copy = $tools->duplicate($this->rfq()->load('items'), $this->me());
        session()->flash('toast', "Draft {$copy->number} created as a copy. Set the deadline, then publish.");

        return $this->redirectRoute('rfqs.edit', $copy->number, navigate: true);
    }

    public function reopen(\App\Services\RfqToolsService $tools): void
    {
        if ($this->attempt(fn () => $tools->reopen($this->rfq(), $this->me(), $this->tool['deadline'], $this->tool['reason']), 'Request opened again. The suppliers were told.')) {
            $this->reset('tool');
            $this->modal = null;
        }
    }

    public function reassign(\App\Services\RfqToolsService $tools): void
    {
        if ($this->attempt(fn () => $tools->reassign($this->rfq(), $this->me(), (int) $this->tool['buyer']), 'Request assigned.')) {
            $this->reset('tool');
            $this->modal = null;
        }
    }

    public function adjust(\App\Services\RfqToolsService $tools): void
    {
        $quote = $this->rfq()->quotes()->find($this->tool['quote']);
        if (! $quote) {
            $this->toast('Choose a quotation.', 'alert');

            return;
        }
        if ($this->attempt(fn () => $tools->adjustQuote($quote, $this->me(), $this->tool['target'], $this->tool['reason']), 'Quotation adjusted. The supplier was told and the original total is kept.')) {
            $this->reset('tool');
            $this->modal = null;
        }
    }

    public function answer(\App\Services\QaService $qa, int $id): void
    {
        $this->need('rfqs', 'E');
        $q = \App\Models\RfqQuestion::where('rfq_id', $this->rfq()->id)->findOrFail($id);
        if ($this->attempt(fn () => $qa->answer($q, $this->me(), $this->reply[$id] ?? ''), 'Answer sent to every invited supplier.')) {
            unset($this->reply[$id]);
        }
    }

    public function requestAssessment(\App\Services\AssessmentService $service): void
    {
        $this->need('field', 'A');
        $fa = null;
        if ($this->attempt(function () use ($service, &$fa) {
            $rfq = $this->rfq();
            $supplier = \App\Models\Supplier::whereIn('id', $rfq->invitations()->select('supplier_id'))->find($this->fa['supplier_id']) ?? throw new \App\Exceptions\BusinessRule('Choose one of the invited suppliers.');
            $fa = $service->request($this->me(), $supplier, $this->fa['title'], \App\Models\FieldAssessment::DEFAULT_CHECKLIST, $rfq, null, $this->fa['due_on']);
        })) {
            $this->toast("Field assessment {$fa->number} requested.");
            $this->modal = null;
        }
    }

    public function mount(string $number): void
    {
        abort_unless($this->me()->isSourcing(), 403);
        $this->number = $this->rfq()->number;
    }

    protected function rfq(): Rfq
    {
        return Rfq::visibleTo($this->me())->where('number', $this->number)->firstOrFail();
    }

    public function publish(RfqService $service): void
    {
        $this->need('rfqs', 'E');
        $this->attempt(fn () => $service->publish($this->rfq()), 'Published. Invited suppliers have been notified.');
    }

    public function closeQuoting(RfqService $service): void
    {
        $this->need('rfqs', 'E');
        if ($this->attempt(fn () => $service->close($this->rfq()), 'Closed to quotations. You can compare and award now.')) {
            $this->close();
        }
    }

    public function cancel(RfqService $service): void
    {
        $this->need('rfqs', 'E');
        if ($this->attempt(fn () => $service->cancel($this->rfq(), $this->reason), 'Request cancelled.')) {
            $this->reset('reason');
            $this->close();
        }
    }

    public function render(RfqService $service)
    {
        $rfq = $this->rfq()->load('items', 'buyer', 'category', 'invitations.supplier', 'purchaseOrders.supplier');
        $quotes = $rfq->quotes()->with('supplier', 'items', 'attachments')->whereIn('status', ['submitted', 'won', 'lost', 'withdrawn'])->orderBy('total_cents')->get();

        $ids = Rfq::visibleTo($this->me())->orderByDesc('id')->pluck('number')->all();
        $n = array_search($rfq->number, $ids, true);

        return view('livewire.rfqs.show', [
            'pager' => $n === false ? null : ['n' => $n + 1, 'of' => count($ids), 'prev' => $n > 0 ? route('rfqs.show', $ids[$n - 1]) : null, 'next' => $n < count($ids) - 1 ? route('rfqs.show', $ids[$n + 1]) : null],
            'files' => $rfq->attachments()->with('uploader')->latest('id')->get(), 'buyers' => in_array($this->modal, ['reassign'], true) ? \App\Models\User::whereIn('role', ['admin', 'buyer'])->where('status', 'active')->orderBy('name')->pluck('name', 'id') : collect(),
            'rfq' => $rfq, 'qa' => $rfq->questions()->with('supplier')->latest('id')->get(), 'assessments' => \App\Models\FieldAssessment::where('rfq_id', $rfq->id)->with('supplier')->latest('id')->get(),
            'quotes' => $quotes,
            'live' => $quotes->whereIn('status', ['submitted', 'won', 'lost']),
            'blockers' => $rfq->status === 'draft' ? $service->publishBlockers($rfq) : [],
            'logs' => $this->tab === 'activity' ? ActivityLog::where('subject_type', 'rfq')->where('subject_id', $rfq->id)->latest('created_at')->limit(60)->get() : collect(),
        ])->title($rfq->number);
    }
}
