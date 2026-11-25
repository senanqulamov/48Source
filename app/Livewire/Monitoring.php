<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Livewire\Concerns\WithTable;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Services\ReminderService;
use App\Support\ReminderText;
use App\Support\Sheet;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Monitoring & SLA')]
class Monitoring extends Component
{
    use Page, WithTable {
        WithTable::export as tableExport;
    }

    /** Days allowed between closing a request and awarding it. */
    public const AWARD_TARGET_DAYS = 5;

    public string $tab = 'requests';

    /** monitoring or sla */
    public string $page = 'monitoring';

    public function mount(string $page = 'monitoring'): void
    {
        $this->page = $page === 'sla' ? 'sla' : 'monitoring';
        foreach (\App\Support\Setting::SLA as $key => $rule) {
            $this->rules[str_replace('.', '_', $key)] = \App\Support\Setting::int($key);
        }
        $this->tab = $this->page === 'sla' ? 'sla' : (in_array($this->tab, ['requests', 'actions'], true) ? $this->tab : 'requests');
        if ($this->page === 'sla') {
            $this->status = 'action';
        }
        $this->need('reports');
        abort_unless($this->me()->isSourcing(), 403);
    }

    /** The SLA rules as typed into the form on the SLA tracker. */
    public array $rules = [];

    #[Url(except: '')]
    public string $kind = '';

    public function saveRules(): void
    {
        $this->need('system', 'E');
        $checks = [];
        $names = [];
        foreach (\App\Support\Setting::SLA as $key => [$label, , $min, $max]) {
            $field = str_replace('.', '_', $key);
            $checks["rules.{$field}"] = "required|integer|min:{$min}|max:{$max}";
            $names["rules.{$field}"] = mb_strtolower($label);
        }
        $this->validate($checks, [], $names);
        foreach (\App\Support\Setting::SLA as $key => $rule) {
            \App\Support\Setting::set($key, (int) $this->rules[str_replace('.', '_', $key)]);
        }
        \App\Support\Audit::log('Settings', 'Changed the SLA rules');
        $this->toast('SLA rules saved. They apply from the next check.');
    }

    public function runReminders(ReminderService $reminders): void
    {
        $this->need('system', 'E');
        $n = $reminders->run();
        $this->toast($n ? "{$n} reminder(s) sent." : 'Nothing new to remind. Each reminder is sent once.');
    }

    public function updatedKind(): void
    {
        $this->resetPage('hist');
    }

    public function clearFilters(): void
    {
        $this->q = '';
        $this->status = $this->page === 'sla' ? 'action' : '';
        $this->f = [];
        $this->selected = [];
        $this->resetPage();
    }

    /** @return \Illuminate\Support\Collection<int, array> */
    private function filteredRows()
    {
        $rows = $this->rows();
        if ($this->status === 'action') {
            $rows = $rows->whereIn('risk.0', ['bad', 'warn']);
        } elseif ($this->status === 'ok') {
            $rows = $rows->where('risk.0', 'ok');
        }
        $stage = $this->facet('stage', ['open', 'closed']);
        $risk = $this->facet('risk', ['bad', 'warn', 'ok']);
        $owners = $this->facet('owner', $rows->pluck('rfq.buyer_id')->map(fn ($id) => (string) $id)->all());
        if ($stage) {
            $rows = $rows->whereIn('rfq.status', $stage);
        }
        if ($risk) {
            $rows = $rows->whereIn('risk.0', $risk);
        }
        if ($owners) {
            $rows = $rows->whereIn('rfq.buyer_id', $owners);
        }
        $query = mb_strtolower(trim($this->q));
        if ($query !== '') {
            $rows = $rows->filter(fn ($x) => str_contains(mb_strtolower($x['rfq']->title.' '.$x['rfq']->number.' '.$x['rfq']->buyer->name), $query));
        }

        $key = $this->sortCol('due');
        $rows = $rows->sortBy(fn ($x) => match ($key) {
            'title' => $x['rfq']->title, 'owner' => $x['rfq']->buyer->name, 'used' => $x['pct'], 'status' => $x['risk'][0], default => $x['due']->timestamp
        }, $this->sortDir() === 'desc' ? SORT_REGULAR : SORT_REGULAR, $this->sortDir() === 'desc');

        return $rows->values();
    }

    private function paginate(\Illuminate\Support\Collection $items, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = $this->getPage($pageName);

        return new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, ['path' => request()->url(), 'pageName' => $pageName]);
    }

    private function history()
    {
        $prefixes = ['rfq' => 'rfq-closing:%', 'po' => 'po-%', 'inv' => 'inv-due:%', 'pr' => 'pr-wait:%', 'doc' => 'doc-exp:%'];
        $query = \App\Models\Reminder::latest('id');
        if (isset($prefixes[$this->kind])) {
            $query->where('key', 'like', $prefixes[$this->kind]);
        }

        return $query->get();
    }

    /** @return \Illuminate\Support\Collection<int, array> */
    private function rows()
    {
        return Rfq::visibleTo($this->me())->whereIn('status', ['open', 'closed'])->with('buyer')->withCount('liveQuotes', 'invitations')->get()->map(function (Rfq $r) {
            if ($r->status === 'open') {
                $start = $r->published_at ?? $r->created_at;
                $due = $r->deadline_at;
                $stage = 'Collecting quotations';
            } else {
                $start = $r->closed_at ?? $r->deadline_at ?? $r->updated_at;
                $due = $start->copy()->addDays(\App\Support\Setting::int('sla.award_days'));
                $stage = 'Comparing, award due';
            }
            $span = max(1, $start->diffInMinutes($due, false));
            $pct = (int) max(0, min(100, intdiv($start->diffInMinutes(now(), false) * 100, $span)));
            $late = $due->isPast();
            $risk = match (true) {
                $late => ['bad', $r->status === 'open' ? 'Deadline passed' : 'Award overdue'],
                $r->status === 'open' && $r->live_quotes_count === 0 && $pct >= 50 => ['bad', 'No quotations yet'],
                $r->status === 'open' && $r->live_quotes_count < 2 && $pct >= 70 => ['warn', 'Fewer than two quotations'],
                $pct >= 80 => ['warn', 'Close to the limit'],
                default => ['ok', 'On track'],
            };

            return ['rfq' => $r, 'stage' => $stage, 'due' => $due, 'pct' => $pct, 'late' => $late, 'risk' => $risk];
        })->sortBy([fn ($a, $b) => ['bad' => 0, 'warn' => 1, 'ok' => 2][$a['risk'][0]] <=> ['bad' => 0, 'warn' => 1, 'ok' => 2][$b['risk'][0]], fn ($a, $b) => $a['due'] <=> $b['due']])->values();
    }

    public function export(string $format = 'xlsx')
    {
        if ($this->page === 'sla') {
            return $this->tableExport($format);
        }

        return Sheet::download('monitoring', ['RFQ', 'Title', 'Stage', 'Owner', 'Due', 'SLA used %', 'Status'], $this->filteredRows()->map(fn ($x) => [$x['rfq']->number, $x['rfq']->title, $x['stage'], $x['rfq']->buyer->name, $x['due']->format('Y-m-d H:i'), $x['pct'], $x['risk'][1]])->all());
    }

    protected function sortable(): array
    {
        return ['title', 'owner', 'due', 'used', 'status'];
    }

    protected function exportData(): array
    {
        return ['sla-deadlines', ['RFQ', 'Title', 'Stage', 'Owner', 'Due', 'SLA used %', 'Status'], $this->filteredRows()->map(fn ($x) => [$x['rfq']->number, $x['rfq']->title, $x['stage'], $x['rfq']->buyer->name, $x['due']->format('Y-m-d H:i'), $x['pct'], $x['risk'][1]])->all()];
    }

    public function render()
    {
        $u = $this->me();
        $actions = collect();
        foreach (PurchaseOrder::visibleTo($u)->where('status', 'issued')->where('issued_at', '<=', now()->subDays(\App\Support\Setting::int('sla.po_ack_days')))->with('supplier')->get() as $p) {
            $actions->push([$p->number, route('orders.show', $p), "Not acknowledged by {$p->supplier->name} for ".(int) $p->issued_at->diffInDays(now()).' days', 'warn']);
        }
        foreach (PurchaseOrder::visibleTo($u)->whereIn('status', ['acknowledged', 'in_transit'])->whereDate('expected_at', '<', now()->toDateString())->with('supplier')->get() as $p) {
            $actions->push([$p->number, route('orders.show', $p), "Delivery from {$p->supplier->name} was due {$p->expected_at->format('j M')}", 'bad']);
        }
        foreach (Invoice::visibleTo($u)->where('status', 'disputed')->with('supplier')->get() as $i) {
            $actions->push([$i->number, route('invoices.show', $i), "Invoice from {$i->supplier->name} does not match", 'warn']);
        }
        foreach (Invoice::visibleTo($u)->where('status', 'approved')->whereDate('due_on', '<', now()->toDateString())->with('supplier')->get() as $i) {
            $actions->push([$i->number, route('invoices.show', $i), "Payment to {$i->supplier->name} is overdue since {$i->due_on->format('j M')}", 'bad']);
        }
        foreach (Requisition::visibleTo($u)->where('status', 'pending')->where('submitted_at', '<=', now()->subDays(\App\Support\Setting::int('sla.requisition_days')))->get() as $q) {
            $actions->push([$q->number, route('requisitions.show', $q), 'Waiting for approval longer than the SLA rule allows', 'warn']);
        }

        $rows = $this->rows();
        if ($this->page === 'sla') {
            $filtered = $this->filteredRows();
            $history = $this->history();
            $historyText = ReminderText::describe($history);
            $deadlineRows = $this->paginate($filtered, $this->perPage());
            $historyRows = $this->paginate($history, 8, 'hist');
            $overdue = $rows->filter(fn ($x) => $x['risk'][0] === 'bad' && $x['late'])->count();
            $atRisk = $rows->filter(fn ($x) => ! ($x['risk'][0] === 'bad' && $x['late']) && in_array($x['risk'][0], ['bad', 'warn'], true))->count();
            $strip = [
                ['l' => 'Tracked requests', 'v' => $rows->count(), 's' => $rows->where('rfq.status', 'open')->count().' quoting · '.$rows->where('rfq.status', 'closed')->count().' awaiting award'],
                ['l' => 'Overdue', 'v' => $overdue, 's' => 'past the deadline or award target', 'c' => 'bad'],
                ['l' => 'At risk', 'v' => $atRisk, 's' => 'need action before the deadline', 'c' => 'warn'],
                ['l' => 'Reminders sent', 'v' => \App\Models\Reminder::where('created_at', '>=', now()->subDays(7))->count(), 's' => 'in the last 7 days'],
            ];

            return view('livewire.monitoring', compact('strip', 'rows', 'actions', 'deadlineRows', 'historyRows', 'historyText') + ['beat' => Cache::get('scheduler:heartbeat'), 'target' => \App\Support\Setting::int('sla.award_days')]);
        }
        $week = now()->addDays(7);
        $strip = [
            ['l' => 'Active RFQs', 'v' => $rows->count(), 's' => $rows->where('rfq.status', 'open')->count().' quoting · '.$rows->where('rfq.status', 'closed')->count().' in evaluation'],
            ['l' => 'At risk', 'v' => $rows->where('risk.0', 'warn')->count(), 's' => 'need action before the deadline', 'c' => 'warn'],
            ['l' => 'Overdue or breached', 'v' => $rows->where('risk.0', 'bad')->count(), 's' => 'past the deadline or the award target', 'c' => 'bad'],
            ['l' => 'Due within 7 days', 'v' => $rows->filter(fn ($x) => $x['due']->isFuture() && $x['due']->lte($week))->count(), 's' => 'deadlines and award targets'],
        ];

        return view('livewire.monitoring', ['strip' => $strip, 'rows' => $rows, 'actions' => $actions, 'target' => \App\Support\Setting::int('sla.award_days'),
            'reminders' => collect(), 'beat' => Cache::get('scheduler:heartbeat')]);
    }
}
