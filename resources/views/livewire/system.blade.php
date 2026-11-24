<div>
    @php
        $head = [
            'health' => ['System health', 'Whether each service the application depends on is working, and the currencies in use.'],
            'flags' => ['Feature flags', 'Optional features for the whole organisation. A change takes effect at once and is recorded.'],
            'impex' => ['Import / export', 'Bring suppliers in from Excel or CSV, and see where each list can be exported.'],
        ][$page];
    @endphp
    <x-ph :title="$head[0]" :desc="$head[1]" :crumbs="[[$head[0]]]">
        @if ($tab === 'health')<button type="button" class="btn" wire:click="$refresh"><x-icon name="refresh" />Re-check now</button>@endif
    </x-ph>
    @if ($page === 'health')<x-strip :items="$strip" />@endif
    @if ($page === 'health')<x-tabs :on="$tab" :tabs="['health' => 'Services', 'currencies' => ['Currencies', $currencies->where('active', true)->count()]]" />@endif
    @if ($tab === 'health')
        <div class="grid g-2">
            <x-card title="Services" sub="Checked each time this page opens" :flush="true"><ul class="list">@foreach ($health as $h)<li data-plain><div class="grow"><div class="t1">{{ $h['name'] }}</div><div class="muted sm">{{ $h['note'] }}</div></div><x-badge :status="$h['ok'] ? 'ok' : 'warn'" :label="$h['ok'] ? 'Operational' : 'Needs attention'" /></li>@endforeach</ul></x-card>
            <div class="stack">
                <x-card title="Feature flags" sub="Changes take effect at once and are logged" :flush="true"><ul class="list">
                    @foreach ($flags as $key => $f)<li wire:key="hfl-{{ $key }}" data-plain><div class="grow"><div class="t1">{{ $f['label'] }}</div></div><x-badge :status="$f['on'] ? 'ok' : 'draft'" :label="$f['on'] ? 'On' : 'Off'" />@perm('system', 'E')<button type="button" class="btn btn-sm" wire:click="toggleFlag('{{ $key }}')">{{ $f['on'] ? 'Switch off' : 'Switch on' }}</button>@endperm</li>@endforeach
                </ul></x-card>
                <x-card title="Import & export">
                    <div class="row wrap" style="gap:8px">
                        <a class="btn" href="{{ route('impex.index') }}" wire:navigate><x-icon name="upload" />Import suppliers</a>
                        <a class="btn" href="{{ route('accounting.index') }}" wire:navigate><x-icon name="download" />Accounting export</a>
                        <a class="btn" href="{{ route('rfqs.index') }}" wire:navigate><x-icon name="download" />Export RFQs</a>
                    </div>
                    <p class="help" style="margin-top:10px">Imports accept .xlsx and .csv with the 48Source templates. Every list exports what it shows.</p>
                </x-card>
            </div>
        </div>
    @elseif ($tab === 'flags')
        <x-card title="Feature flags" sub="Changes take effect at once and are logged" :flush="true"><ul class="list">
            @foreach ($flags as $key => $f)<li wire:key="fl-{{ $key }}"><div class="grow"><div class="t1">{{ $f['label'] }}</div><div class="muted sm mono">{{ $key }}</div></div><x-badge :status="$f['on'] ? 'ok' : 'draft'" :label="$f['on'] ? 'On' : 'Off'" />@perm('system', 'E')<button type="button" class="btn btn-sm" wire:click="toggleFlag('{{ $key }}')">{{ $f['on'] ? 'Switch off' : 'Switch on' }}</button>@endperm</li>@endforeach
        </ul></x-card>
    @elseif ($tab === 'currencies')
        <div class="grid g-2-1">
            <x-card title="Currencies" sub="Active currencies can be chosen on requests, quotations and contracts" :flush="true"><ul class="list">
                @foreach ($currencies as $c)<li wire:key="cu-{{ $c->code }}"><b class="mono" style="width:48px">{{ $c->code }}</b><div class="grow t1">{{ $c->name }}</div><x-badge :status="$c->active ? 'ok' : 'draft'" :label="$c->active ? 'Active' : 'Not offered'" />@perm('system', 'E')<button type="button" class="btn btn-sm" wire:click="toggleCurrency('{{ $c->code }}')">{{ $c->active ? 'Deactivate' : 'Activate' }}</button>@endperm</li>@endforeach
            </ul><div class="card-b muted sm" style="border-top:1px solid var(--line)">Quotations in different currencies cannot be compared or awarded together. Amounts are never converted.</div></x-card>
            @perm('system', 'E')<x-card title="Add a currency"><div class="stack" style="gap:14px"><x-field label="Code" for="cu-c" help="Three letters, for example GBP."><input id="cu-c" class="input mono" maxlength="3" wire:model="cur.code"></x-field><x-field label="Name" for="cu-n"><input id="cu-n" class="input" wire:model="cur.name"></x-field><button type="button" class="btn btn-p" wire:click="addCurrency">Add currency</button></div></x-card>@endperm
        </div>
    @else
        <div class="grid g-2-1">
            <x-card title="Import suppliers" sub="Excel or CSV, checked row by row">
                <p style="margin-bottom:12px">First row: <span class="mono">name, email, city, contact, phone, category</span>. New suppliers are added as "Pending approval". A name that already exists is skipped.</p>
                <div class="row" style="margin-bottom:12px;flex-wrap:wrap"><input type="file" class="input" style="padding:8px;flex:1;min-width:220px" wire:model="file" accept=".xlsx,.csv" aria-label="Suppliers file"><button type="button" class="btn btn-g" wire:click="template"><x-icon name="download" />Template</button>@perm('suppliers', 'C')<button type="button" class="btn btn-p" wire:click="importSuppliers" wire:loading.attr="disabled" wire:target="file,importSuppliers">Import</button>@endperm</div>
                @error('file')<span class="errt">{{ $message }}</span>@enderror
                @if ($report)<x-note :kind="$report['errors'] ? 'warn' : 'ok'"><b>{{ $report['saved'] }} supplier(s) added.</b>@if ($report['errors']) {{ count($report['errors']) }} row(s) were skipped:<ul style="margin:6px 0 0 18px">@foreach ($report['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>@endif</x-note>@endif
                <h3 class="lbl" style="margin:18px 0 8px">Other imports and exports</h3>
                <ul style="margin:0;padding-left:18px;display:grid;gap:4px"><li>Stores import their price list under My products.</li><li>Every list exports what it shows as Excel or CSV from its download button.</li><li>Approved invoices go to accounting under Accounting export.</li></ul>
            </x-card>
            <x-card title="Recent imports" :flush="true">@if ($transfers->isEmpty())<div class="card-b faint">No imports yet.</div>@else<ul class="list">@foreach ($transfers as $t)<li><div class="grow"><div class="t1">{{ $t->action }}</div><div class="muted sm">{{ $t->actor_name }} · {{ $t->created_at->format('j M Y, H:i') }}</div></div></li>@endforeach</ul>@endif</x-card>
        </div>
    @endif
</div>
