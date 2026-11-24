<?php

namespace App\Livewire;

use App\Livewire\Concerns\Page;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Rfq;
use App\Services\ReminderService;
use App\Support\Sheet;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Monitoring & SLA')]
class Monitoring extends Component
{
    use Page;

    /** Days allowed between closing a request and awarding it. */
    public const AWARD_TARGET_DAYS = 5;

    #[Url(except: 'requests')]
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
        $this->need('reports');
        abort_unless($this->me()->isSourcing(), 403);
    }

    /** The SLA rules as typed into the form on the SLA tracker. */
    public array $rules = [];

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

    public function export()
    {
        return Sheet::download('monitoring', ['RFQ', 'Title', 'Stage', 'Owner', 'Due', 'SLA used %', 'Status'], $this->rows()->map(fn ($x) => [$x['rfq']->number, $x['rfq']->title, $x['stage'], $x['rfq']->buyer->name, $x['due']->format('Y-m-d H:i'), $x['pct'], $x['risk'][1]])->all());
    }

    public function render()
    {
        $u = $this->me();
        $actions = collect();
        foreach (PurchaseOrder::visibleTo($u)->where('status', 'issued')->where('issued_at', '<=', now()->subDays(\App\Support\Setting::int('sla.po_ack_days')))->with('supplier')->get() as $p) {
            $actions->push([$p->number, route('orders.show', $p), "Not acknowledged by {$p->supplier->name} for " . (int) $p->issued_at->diffInDays(now()) . ' days', 'warn']);
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
        $week = now()->addDays(7);
        $strip = $this->page === 'sla' ? [
            ['l' => 'Tracked RFQs', 'v' => $rows->count(), 's' => 'open or in evaluation'],
            ['l' => 'Reminders sent today', 'v' => \App\Models\Reminder::whereDate('created_at', now()->toDateString())->count(), 's' => 'each one is sent once'],
            ['l' => 'At risk', 'v' => $rows->where('risk.0', 'warn')->count(), 's' => 'need action before the deadline', 'c' => 'warn'],
            ['l' => 'Overdue or breached', 'v' => $rows->where('risk.0', 'bad')->count(), 's' => 'past the award target', 'c' => 'bad', 'href' => route('monitoring.index')],
        ] : [
            ['l' => 'Active RFQs', 'v' => $rows->count(), 's' => $rows->where('rfq.status', 'open')->count().' quoting · '.$rows->where('rfq.status', 'closed')->count().' in evaluation'],
            ['l' => 'At risk', 'v' => $rows->where('risk.0', 'warn')->count(), 's' => 'need action before the deadline', 'c' => 'warn'],
            ['l' => 'Overdue or breached', 'v' => $rows->where('risk.0', 'bad')->count(), 's' => 'past the deadline or the award target', 'c' => 'bad'],
            ['l' => 'Due within 7 days', 'v' => $rows->filter(fn ($x) => $x['due']->isFuture() && $x['due']->lte($week))->count(), 's' => 'deadlines and award targets'],
        ];

        return view('livewire.monitoring', ['strip' => $strip, 'rows' => $rows, 'actions' => $actions, 'target' => \App\Support\Setting::int('sla.award_days'),
            'reminders' => $this->tab === 'sla' ? \App\Models\Reminder::latest('id')->limit(60)->get() : collect(), 'beat' => \Illuminate\Support\Facades\Cache::get('scheduler:heartbeat')]);
    }
}
