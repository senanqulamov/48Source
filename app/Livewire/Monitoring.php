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

    public function mount(): void
    {
        $this->need('reports');
        abort_unless($this->me()->isSourcing(), 403);
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
                $due = $start->copy()->addDays(self::AWARD_TARGET_DAYS);
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
        foreach (PurchaseOrder::visibleTo($u)->where('status', 'issued')->where('issued_at', '<=', now()->subDays(3))->with('supplier')->get() as $p) {
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
        foreach (Requisition::visibleTo($u)->where('status', 'pending')->where('submitted_at', '<=', now()->subDays(2))->get() as $q) {
            $actions->push([$q->number, route('requisitions.show', $q), 'Waiting for approval for more than two days', 'warn']);
        }

        return view('livewire.monitoring', ['rows' => $this->rows(), 'actions' => $actions, 'target' => self::AWARD_TARGET_DAYS,
            'reminders' => $this->tab === 'sla' ? \App\Models\Reminder::latest('id')->limit(60)->get() : collect(), 'beat' => \Illuminate\Support\Facades\Cache::get('scheduler:heartbeat')]);
    }
}
