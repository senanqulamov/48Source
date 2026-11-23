@php use App\Support\Money; @endphp
<div>
    <x-ph title="Spend & reports" desc="Committed spend from issued purchase orders, budgets by category, savings on awarded requests and service figures." :crumbs="[['Spend & reports']]">
        <div style="width:110px"><select class="select" wire:model.live="year" aria-label="Year">@foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</select></div>
        <button type="button" class="btn" wire:click="export"><x-icon name="download" />Excel</button>
    </x-ph>
    <x-tabs :on="$tab" :tabs="['spend' => 'Spend', 'budgets' => 'Budgets', 'savings' => 'Savings', 'kpis' => 'Service figures']" />
    @if ($tab === 'spend')
        <x-card title="Committed spend by month" :sub="$year" style="margin-bottom:16px">
            <div class="row" style="align-items:baseline;margin-bottom:4px"><span class="strip-v" style="font-size:30px" data-done="1">${{ number_format(intdiv($spend['total'], 100)) }}</span><span class="muted sm">{{ $spend['count'] }} purchase order{{ $spend['count'] === 1 ? '' : 's' }} · hover the chart for each month</span></div>{!! $area !!}</x-card>
        <div class="grid g-2"><x-card title="By supplier" sub="Top six">{!! $bySupplier !!}</x-card><x-card title="By category" sub="Top six">{!! $byCategory !!}</x-card></div>
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
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">@foreach ($kpis as $name => $k)<x-card><div class="muted sm">{{ $name }}</div><div class="strip-v" style="font-size:32px;color:var(--text)" data-done="1">{{ $k['value'] }}</div><div class="muted sm">{{ $k['note'] }}</div></x-card>@endforeach</div>
    @endif
    @if ($modal === 'budget')
        <x-modal title="Set a budget for {{ $year }}"><div class="stack" style="gap:14px"><x-field label="Category" for="bd-c"><select id="bd-c" class="select" wire:model="b.category_id"><option value="">Choose a category</option>@foreach ($categories as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>
            <x-field label="Yearly budget" for="bd-a" help="Saving again for the same category replaces the amount."><input id="bd-a" class="input mono" inputmode="decimal" wire:model="b.amount" placeholder="0.00"></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="saveBudget">Save budget</button></x-slot:foot></x-modal>
    @endif
</div>
