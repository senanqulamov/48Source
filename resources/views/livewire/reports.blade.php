@php use App\Support\Money; @endphp
<div>
    @php
        $head = [
            'spend' => ['Spend & budgets', 'Where the money goes: by category, supplier and department, how much is under contract, and how each budget is holding.'],
            'savings' => ['Savings', 'What each awarded request saved against its budget and against the average quotation.'],
            'kpis' => ['KPI reports', 'Calculated from your requests, quotations and purchase orders for the chosen period. Award a request or add a quotation and the figures change.'],
        ][$page];
    @endphp
    <x-ph :title="$head[0]" :desc="$head[1]" :crumbs="[[$head[0]]]">
        @if ($page === 'kpis')<div class="seg" role="group" aria-label="Period">@foreach ([3, 6, 12] as $m)<button type="button" class="{{ $period === $m ? 'on' : '' }}" aria-pressed="{{ $period === $m ? 'true' : 'false' }}" wire:click="$set('period', {{ $m }})">{{ $m }} months</button>@endforeach</div>
        @else<div style="width:110px"><select class="select" wire:model.live="year" aria-label="Year">@foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></div>@endif
        <button type="button" class="btn" wire:click="export"><x-icon name="download" />Excel</button>
    </x-ph>
    @if ($page === 'spend')
        @php
            $short = fn (int $cents) => $cents >= 100000000 ? '$' . number_format($cents / 100000000, 2) . 'M' : Money::format($cents, 'USD', false);
            $k = fn (int $cents) => '$' . number_format($cents / 100000) . 'k';
            $pct = $an['total'] ? (int) round($an['covered'] * 100 / $an['total']) : 0;
            $rowsOf = fn ($list, int $take = 7) => $list->take($take)->map(fn ($r) => [$r['name'], $r['cents'], $k($r['cents'])])->all();
            $top = $an['bySupplier']->first();
            $share = $top && $an['total'] ? (int) round($top['cents'] * 100 / $an['total']) : 0;
        @endphp
        <x-strip :items="[
            ['l' => 'Total spend', 'v' => $short($an['total']), 's' => $an['count'] . ' orders'],
            ['l' => 'Under contract', 'v' => $pct . '%', 's' => Money::format($an['total'] - $an['covered'], 'USD', false) . ' outside contracts'],
            ['l' => 'Paid', 'v' => $short($an['paid']), 's' => $an['paidCount'] . ' invoices'],
            ['l' => 'Budgets exceeded', 'v' => $over, 's' => 'of ' . $budgetCount . ' categories', 'c' => $over ? 'bad' : ''],
        ]" />
        <x-tabs :on="$tab" :tabs="['spend' => 'Analysis', 'budgets' => 'Budgets']" />
    @endif
    @if ($tab === 'spend')
        <div class="grid g-2">
            <x-card title="Spend by month" :sub="'$ thousand, ' . $year">{!! \App\Support\Chart::bars(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], array_map(fn ($c) => (int) round($c / 100000), array_values($an['months'])), null, '$') !!}</x-card>
            <x-card title="Spend by category">{!! \App\Support\Chart::hbars($rowsOf($an['byCategory']), 'No spend yet.') !!}</x-card>
            <x-card title="Top suppliers" :sub="$share > 30 ? $top['name'] . ' holds ' . $share . '% - dependency risk' : 'No supplier above 30% of spend'">{!! \App\Support\Chart::hbars($rowsOf($an['bySupplier']), 'No spend yet.') !!}</x-card>
            <x-card title="Spend by department">{!! \App\Support\Chart::hbars($rowsOf($an['byDepartment']), 'No spend yet.') !!}</x-card>
        </div>
        <div style="margin-top:16px">
            <x-card title="Spend records" :sub="$an['count'] . ' records'" :flush="true">
                @if ($an['records']->isEmpty())<x-empty icon="chart" title="No spend in {{ $year }}" text="Issued purchase orders appear here." />
                @else<div class="tbl-wrap"><table class="tbl compact"><thead><tr><th>Record</th><th class="hide-sm">Type</th><th>Supplier</th><th class="hide-md">Category</th><th class="hide-md">Department</th><th>Date</th><th class="num">Amount</th></tr></thead><tbody>
                    @foreach ($an['records']->take(100) as $o)
                        <tr class="click" data-href="{{ route('orders.show', $o) }}" tabindex="0"><td class="mono b">{{ $o->number }}</td><td class="hide-sm">Purchase order</td><td class="t1">{{ $o->supplier->name }}</td><td class="hide-md">{{ $o->rfq->category?->name ?? 'No category' }}</td><td class="hide-md">{{ $o->buyer?->department ?: 'No department' }}</td><td style="white-space:nowrap">{{ $o->created_at->format('j M') }}</td><td class="num mono b">{{ Money::format($o->total_cents, $o->currency, false) }}</td></tr>
                    @endforeach
                </tbody></table></div>
                @if ($an['count'] > 100)<div class="pager">Showing the latest 100 of {{ $an['count'] }} records. Export for the full list.</div>@endif
                @endif
            </x-card>
        </div>
    @elseif ($tab === 'budgets')
        <x-card title="Budgets by category" :sub="$year . ' · against committed spend'" :flush="true">
            <x-slot:act>@perm('spend', 'E')<button type="button" class="btn btn-sm" wire:click="open('budget')"><x-icon name="plus" :size="14" />Set a budget</button>@endperm</x-slot:act>
            @if ($budgets->isEmpty())<x-empty icon="chart" title="No budgets for {{ $year }}" text="Set a yearly budget for a category to see how much of it is committed." />
            @else<div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th>Category</th><th class="num">Budget</th><th class="num">Committed</th><th class="num">Left</th><th>Used</th></tr></thead><tbody>
                @foreach ($budgets as $b)<tr><td class="t1">{{ $b['name'] }}</td><td class="num mono">{{ Money::format($b['amount'], 'USD', false) }}</td><td class="num mono b">{{ Money::format($b['used'], 'USD', false) }}</td><td class="num mono" @if ($b['left'] < 0) style="color:var(--bad)" @endif>{{ $b['left'] < 0 ? '-' : '' }}{{ Money::format(abs($b['left']), 'USD', false) }}</td>
                    <td style="min-width:180px"><div class="row"><span class="mono sm" style="width:44px">{{ $b['pct'] }}%</span><div class="prog {{ $b['pct'] > 100 ? 'bad' : ($b['pct'] >= 80 ? 'warn' : 'ok') }}" style="flex:1"><i style="width:{{ min(100, $b['pct']) }}%"></i></div>@if ($b['pct'] > 100)<x-badge status="bad" label="Over" />@endif</div></td></tr>@endforeach
            </tbody></table></div>@endif
        </x-card>
    @elseif ($tab === 'savings')
        <x-strip :items="[['l' => 'Awarded value', 'v' => Money::format($savings['awarded'], 'USD', false), 's' => $savings['rows']->count() . ' awarded requests'], ['l' => 'Saved against budget', 'v' => Money::format(max(0, $savings['vsBudget']), 'USD', false), 's' => 'budget minus awarded value'], ['l' => 'Saved against average quote', 'v' => Money::format(max(0, $savings['vsAverage']), 'USD', false), 's' => 'average complete quotation minus award']]" />
        <x-card title="Awarded requests" :flush="true">
            @if ($savings['rows']->isEmpty())<x-empty icon="award" title="No awards in {{ $year }}" text="Savings are measured when a request is awarded." />
            @else<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Request</th><th class="num">Quotations</th><th class="num">Budget</th><th class="num">Average quote</th><th class="num">Awarded</th><th class="num">Against budget</th><th class="num">Against average</th></tr></thead><tbody>
                @foreach ($savings['rows'] as $r)<tr class="click" data-href="{{ route('rfqs.show', $r['rfq']) }}" tabindex="0"><td class="tmain"><div class="t1">{{ $r['rfq']->title }}</div><div class="t2 mono">{{ $r['rfq']->number }}</div></td><td class="num mono">{{ $r['quotes'] }}</td><td class="num mono">{{ $r['budget'] ? Money::format($r['budget'], 'USD', false) : '-' }}</td><td class="num mono">{{ $r['average'] !== null ? Money::format($r['average'], 'USD', false) : '-' }}</td><td class="num mono b">{{ Money::format($r['awarded'], 'USD', false) }}</td>
                    @foreach (['vsBudget', 'vsAverage'] as $k)<td class="num mono" style="color:var({{ $r[$k] === null ? '--text-3' : ($r[$k] >= 0 ? '--ok' : '--bad') }})">{{ $r[$k] === null ? '-' : ($r[$k] < 0 ? '-' : '') . Money::format(abs($r[$k]), 'USD', false) }}</td>@endforeach</tr>@endforeach
            </tbody></table></div>@endif
        </x-card>
    @else
        @php
            $pub = array_sum($kr['published']); $aw = array_sum($kr['awarded']);
            $part = $kr['invited'] ? (int) round($kr['quoted'] * 100 / $kr['invited']) : 0;
            $avg = $kr['cycle'] ? (int) round(array_sum($kr['cycle']) / count($kr['cycle'])) : null;
            $short = fn (int $cents) => $cents >= 100000000 ? '$' . number_format($cents / 100000000, 2) . 'M' : Money::format($cents, 'USD', false);
        @endphp
        <x-strip :items="[
            ['l' => 'RFQs published', 'v' => $pub, 's' => $aw . ' awarded · ' . ($pub ? (int) round($aw * 100 / $pub) : 0) . '% award rate'],
            ['l' => 'Quotation participation', 'v' => $part . '%', 's' => $kr['quoted'] . ' quotations from ' . $kr['invited'] . ' invitations', 'c' => $part >= 60 ? 'ok' : ''],
            ['l' => 'Awarded value', 'v' => $short($kr['total']), 's' => $kr['orders'] ? Money::format(intdiv($kr['total'], $kr['orders']), 'USD', false) . ' average award' : 'no awards in the period'],
            ['l' => 'Request-to-award time', 'v' => $avg === null ? '-' : $avg . ' d', 's' => $kr['cycle'] ? 'fastest ' . min($kr['cycle']) . ' d · slowest ' . max($kr['cycle']) . ' d' : 'no awarded requests'],
        ]" />
        <div class="grid g-2">
            <x-card title="RFQ trends" sub="Published and awarded per month"><div class="legend" style="margin-bottom:8px"><span><i style="background:var(--primary)"></i>Published</span><span><i style="background:var(--line-2)"></i>Awarded</span></div>{!! \App\Support\Chart::bars($kr['labels'], $kr['published'], $kr['awarded']) !!}</x-card>
            <x-card title="Quotation participation" sub="Share of invitations that became a quotation, %">{!! \App\Support\Chart::bars($kr['labels'], $kr['participation']) !!}</x-card>
            <x-card title="Purchasing activity" sub="$ thousand awarded per month">{!! \App\Support\Chart::bars($kr['labels'], array_map(fn ($c) => (int) round($c / 100000), $kr['volume']), null, '$') !!}</x-card>
            <x-card title="Spend by category">{!! \App\Support\Chart::hbars($kr['byCategory']->take(7)->map(fn ($r) => [$r['name'], $r['cents'], '$' . number_format($r['cents'] / 100000) . 'k'])->all(), 'No spend in the period.') !!}</x-card>
        </div>
        <div style="margin-top:16px">
            <x-card title="Supplier comparison" sub="Invitations, quotations and awards in the period" :flush="true">
                @if ($kr['suppliers']->isEmpty())<x-empty icon="building" title="No supplier activity" text="Suppliers appear here once they are invited to a published request." />
                @else<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Supplier</th><th class="hide-sm">Category</th><th class="num">Invited</th><th class="num">Quoted</th><th>Win rate</th><th class="num">Awarded value</th></tr></thead><tbody>
                    @foreach ($kr['suppliers'] as $sp)
                        <tr class="click" data-href="{{ route('suppliers.show', $sp['id']) }}" tabindex="0"><td class="t1">{{ $sp['name'] }}</td><td class="hide-sm muted">{{ $sp['category'] ?? 'No category' }}</td><td class="num mono">{{ $sp['invited'] }}</td><td class="num mono">{{ $sp['quoted'] }}</td>
                            <td style="min-width:150px"><div class="row"><span class="mono sm" style="width:40px">{{ $sp['win'] }}%</span><div class="prog" style="flex:1"><i style="width:{{ $sp['win'] }}%"></i></div></div></td><td class="num mono b">{{ Money::format($sp['value'], 'USD', false) }}</td></tr>
                    @endforeach
                </tbody></table></div>@endif
            </x-card>
        </div>
        <h3 class="lbl" style="margin:18px 0 8px">Service figures, {{ $year }}</h3>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">@foreach ($kpis as $name => $k)<x-card><div class="muted sm">{{ $name }}</div><div class="strip-v" style="font-size:32px;color:var(--text)" data-done="1">{{ $k['value'] }}</div><div class="muted sm">{{ $k['note'] }}</div></x-card>@endforeach</div>
    @endif
    @if ($modal === 'budget')
        <x-modal title="Set a budget for {{ $year }}"><div class="stack" style="gap:14px"><x-field label="Category" for="bd-c"><select id="bd-c" class="select" wire:model="b.category_id"><option value="">Choose a category</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
            <x-field label="Yearly budget" for="bd-a" help="Saving again for the same category replaces the amount."><input id="bd-a" class="input mono" inputmode="decimal" wire:model="b.amount" placeholder="0.00"></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="saveBudget">Save budget</button></x-slot:foot></x-modal>
    @endif
</div>
