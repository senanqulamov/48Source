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

    /** Target and reason typed into the price adjustment tool of each quotation, by quotation id. */
    public array $adj = [];

    public string $inviteId = '';

    public function remindOne(\App\Services\RfqToolsService $tools, int $supplierId): void
    {
        $this->need('rfqs', 'E');
        $this->attempt(function () use ($tools, $supplierId) {
            $s = $tools->remindOne($this->rfq(), $this->me(), $supplierId);
            $this->toast("Reminder sent to {$s->name}.");
        });
    }

    public function invite(\App\Services\RfqToolsService $tools): void
    {
        $this->need('rfqs', 'E');
        if ($this->attempt(function () use ($tools) {
            $s = $tools->invite($this->rfq(), $this->me(), (int) $this->inviteId);
            $this->toast("{$s->name} invited.");
        })) {
            $this->inviteId = '';
            $this->modal = null;
        }
    }

    /** Admin controls: the status select only starts the normal action for that change, with its own checks and reasons. */
    public function adminStatus(string $to, RfqService $service): void
    {
        abort_unless($this->me()->isAdmin(), 403);
        $from = $this->rfq()->status;
        match (true) {
            $to === $from => null,
            $from === 'draft' && $to === 'open' => $this->publish($service),
            $from === 'open' && $to === 'closed' => $this->open('close'),
            $from === 'closed' && $to === 'open' => $this->open('reopen'),
            $to === 'cancelled' && in_array($from, ['draft', 'open', 'closed'], true) => $this->open('cancel'),
            default => $this->toast('This change is not possible from '.mb_strtolower(Rfq::STATUSES[$from] ?? $from).'.', 'alert'),
        };
    }

    public function adminType(string $type, \App\Services\RfqToolsService $tools): void
    {
        abort_unless($this->me()->isAdmin(), 403);
        $this->attempt(fn () => $tools->setType($this->rfq(), $this->me(), $type), 'Request type changed.');
    }

    public function adminAssessment(bool $on, \App\Services\RfqToolsService $tools): void
    {
        abort_unless($this->me()->isAdmin(), 403);
        $this->attempt(fn () => $tools->requireAssessment($this->rfq(), $this->me(), $on), $on ? 'Field assessment is now required.' : 'Field assessment is no longer required.');
    }

    public function toggleSeal(): void
    {
        $this->need('rfqs', 'E');
        $rfq = $this->rfq();
        abort_unless($this->me()->isAdmin() || $rfq->buyer_id === $this->me()->id, 403);
        if ($rfq->status !== 'draft' && ! ($rfq->status === 'open' && ! $rfq->sealed && ! $rfq->liveQuotes()->exists())) {
            $this->toast($rfq->sealed ? 'Sealed bids stay sealed until the deadline.' : 'Bids can only be sealed before the first quotation arrives.', 'alert');

            return;
        }
        $rfq->update(['sealed' => ! $rfq->sealed]);
        \App\Support\Audit::log('RFQ', $rfq->sealed ? 'Sealed bids switched on' : 'Sealed bids switched off', $rfq, $rfq->number);
        $this->toast($rfq->sealed ? 'Bids are sealed until the deadline.' : 'Bids are no longer sealed.');
    }

    /** The price adjustment tool inside one quotation (administrators only; the service checks it again). */
    public function adjustQuote(\App\Services\RfqToolsService $tools, int $quoteId): void
    {
        $quote = $this->rfq()->quotes()->find($quoteId);
        if (! $quote) {
            return;
        }
        $in = $this->adj[$quoteId] ?? [];
        if ($this->attempt(fn () => $tools->adjustQuote($quote, $this->me(), (string) ($in['target'] ?? ''), (string) ($in['reason'] ?? '')), 'Quotation adjusted. The supplier was told and the original total is kept.')) {
            unset($this->adj[$quoteId]);
        }
    }

    public function exportItems(string $format = 'xlsx')
    {
        $rfq = $this->rfq()->load('items');
        $rows = $rfq->items->map(fn ($i) => [$i->position, $i->name, \App\Support\Money::qtyInput($i->quantity_milli), $i->unit, \App\Support\Money::input($i->target_price_cents),
            $i->target_price_cents ? \App\Support\Money::input(\App\Support\Money::lineTotal($i->target_price_cents, $i->quantity_milli)) : '']);

        return \App\Support\Sheet::download($rfq->number.'-items', ['#', 'Item', 'Quantity', 'Unit', 'Target unit price', 'Estimated total'], $rows, $format === 'csv' ? 'csv' : 'xlsx');
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

    /** Share of delivered orders that arrived on or before the promised date, per supplier. @return array<int, ?int> */
    private function onTime(array $supplierIds): array
    {
        $out = [];
        $orders = \App\Models\PurchaseOrder::whereIn('supplier_id', $supplierIds)->whereNotNull('delivered_at')->whereNotNull('expected_at')->get(['supplier_id', 'delivered_at', 'expected_at']);
        foreach ($orders->groupBy('supplier_id') as $id => $list) {
            $out[$id] = (int) round($list->filter(fn ($o) => $o->delivered_at->startOfDay()->lte($o->expected_at->endOfDay()))->count() * 100 / $list->count());
        }

        return $out;
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
            'latest' => $this->tab === 'overview' ? ActivityLog::where('subject_type', 'rfq')->where('subject_id', $rfq->id)->latest('created_at')->limit(4)->get() : collect(),
            'onTime' => $this->tab === 'suppliers' ? $this->onTime($rfq->invitations->pluck('supplier_id')->all()) : [],
            'canInvite' => in_array($rfq->status, ['draft', 'open'], true) && $this->me()->allowed('rfqs', 'E') && ($this->me()->isAdmin() || $rfq->buyer_id === $this->me()->id),
            'inviteable' => $this->modal === 'invite' ? \App\Models\Supplier::where('status', 'active')->whereNotIn('id', $rfq->invitations->pluck('supplier_id'))->orderBy('name')->limit(500)->pluck('name', 'id') : collect(),
            'faBySupplier' => \App\Models\FieldAssessment::where('rfq_id', $rfq->id)->latest('id')->get()->unique('supplier_id')->keyBy('supplier_id'),
            'openQuestions' => $rfq->questions()->whereNull('answer')->count(),
        ])->title($rfq->number);
    }
}
