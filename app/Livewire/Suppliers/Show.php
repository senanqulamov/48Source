<?php

namespace App\Livewire\Suppliers;

use App\Livewire\Concerns\Page;
use App\Models\ActivityLog;
use App\Models\FieldAssessment;
use App\Models\Supplier;
use App\Models\SupplierAction;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Services\AssessmentService;
use App\Services\SupplierInsightService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Supplier 360')]
class Show extends Component
{
    use Page, WithFileUploads;

    public int $supplierId;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    public array $doc = ['type' => 'registration', 'title' => '', 'expires_on' => ''];

    public $file = null;

    public array $card = ['period' => '', 'quality' => '4', 'delivery' => '4', 'price' => '4', 'service' => '4', 'comment' => ''];

    public array $act = ['title' => '', 'due_on' => '', 'detail' => ''];

    public array $fa = ['title' => 'Site access and unloading survey', 'assignee' => '', 'due_on' => ''];

    public function mount(int $supplier): void
    {
        $u = $this->me();
        // Buyers open any supplier; a supplier opens only its own company profile.
        abort_unless(($u->isSourcing() && $u->allowed('suppliers')) || ($u->role === 'supplier' && $u->supplier_id === $supplier), 403);
        $this->supplierId = Supplier::findOrFail($supplier)->id;
        $this->card['period'] = now()->format('Y') . '-Q' . now()->quarter;
    }

    protected function supplier(): Supplier
    {
        return Supplier::findOrFail($this->supplierId);
    }

    public function addDocument(SupplierInsightService $service): void
    {
        $this->validate(['file' => 'nullable|file|mimes:' . config('procurement.document_mimes') . '|max:' . config('procurement.document_max_kb')], ['file.mimes' => 'Upload a PDF, JPG or PNG file.', 'file.max' => 'The file is larger than 5 MB.']);
        if ($this->attempt(fn () => $service->addDocument($this->supplier(), $this->me(), $this->doc, $this->file), 'Document added.')) {
            $this->reset('doc', 'file');
            $this->modal = null;
        }
    }

    public function verify(SupplierInsightService $service, int $id, bool $ok): void
    {
        $this->need('suppliers', 'A');
        abort_unless($this->me()->isSourcing(), 403);
        $service->verifyDocument(SupplierDocument::where('supplier_id', $this->supplierId)->findOrFail($id), $this->me(), $ok);
        $this->toast($ok ? 'Document verified.' : 'Document rejected. The supplier was told.');
    }

    public function saveCard(SupplierInsightService $service): void
    {
        $this->need('suppliers', 'E');
        abort_unless($this->me()->isSourcing(), 403);
        if ($this->attempt(fn () => $service->score($this->supplier(), $this->me(), $this->card['period'], $this->card, $this->card['comment']), 'Scorecard saved.')) {
            $this->modal = null;
        }
    }

    public function addAction(SupplierInsightService $service): void
    {
        $this->need('suppliers', 'E');
        abort_unless($this->me()->isSourcing(), 403);
        if ($this->attempt(fn () => $service->addAction($this->supplier(), $this->me(), $this->act['title'], $this->act['due_on'], $this->act['detail']), 'Action added. The supplier was told.')) {
            $this->reset('act');
            $this->modal = null;
        }
    }

    public function closeAction(SupplierInsightService $service, int $id): void
    {
        $this->need('suppliers', 'E');
        abort_unless($this->me()->isSourcing(), 403);
        $this->attempt(fn () => $service->completeAction(SupplierAction::where('supplier_id', $this->supplierId)->findOrFail($id)), 'Action closed.');
    }

    public function requestAssessment(AssessmentService $service): void
    {
        $this->need('field', 'A');
        abort_unless($this->me()->isSourcing(), 403);
        $fa = null;
        if ($this->attempt(function () use ($service, &$fa) {
            $fa = $service->request($this->me(), $this->supplier(), $this->fa['title'], FieldAssessment::DEFAULT_CHECKLIST, null, $this->fa['assignee'] ? (int) $this->fa['assignee'] : null, $this->fa['due_on']);
        })) {
            $this->toast("Field assessment {$fa->number} requested.");
            $this->modal = null;
        }
    }

    public function render(SupplierInsightService $service)
    {
        $s = $this->supplier()->load('category', 'users', 'documents', 'scorecards', 'actions.owner', 'contracts', 'assessments');
        $orders = $s->purchaseOrders()->where('status', '!=', 'cancelled')->get(['id', 'status', 'total_cents', 'expected_at', 'delivered_at']);
        $done = $orders->whereNotNull('delivered_at');
        $onTime = $done->filter(fn ($p) => ! $p->expected_at || $p->delivered_at->startOfDay()->lte($p->expected_at))->count();

        return view('livewire.suppliers.show', [
            's' => $s, 'risk' => $service->risk($s), 'sourcing' => $this->me()->isSourcing(),
            'stats' => ['orders' => $orders->count(), 'spend' => (int) $orders->sum('total_cents'), 'ontime' => $done->count() ? intdiv($onTime * 100, $done->count()) : null, 'invited' => $s->invitations()->count(), 'quoted' => $s->quotes()->whereIn('status', ['submitted', 'won', 'lost'])->count()],
            'workers' => $this->modal === 'fa' ? User::where('supplier_id', $s->id)->where('status', 'active')->orderBy('name')->get() : collect(),
            'logs' => $this->tab === 'history' ? ActivityLog::where('supplier_id', $s->id)->latest('created_at')->limit(80)->get() : collect(),
        ])->title($s->name);
    }
}
