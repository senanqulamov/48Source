@php use App\Support\Money; @endphp
<div>
    <x-ph title="Accounting export" desc="Download orders, receipts, invoices, suppliers and contracts as files your ERP or accounting system can import." :crumbs="[['Invoices & receipts', route('invoices.index')], ['Accounting export']]" />
    <section class="card" style="margin-bottom:16px"><div class="toolbar" style="border-bottom:0;align-items:flex-end">
        <div class="field" style="width:190px"><label for="ac-f">Invoice date from</label><input id="ac-f" type="date" class="input" wire:model.live="from"></div>
        <div class="field" style="width:190px"><label for="ac-t">to</label><input id="ac-t" type="date" class="input" wire:model.live="to"></div>
        <label class="row" style="cursor:pointer;height:40px"><input type="checkbox" class="chk" wire:model.live="again">Include invoices already exported</label><span class="sp"></span>
        <button type="button" class="btn" wire:click="download('csv')" @disabled($invoices->isEmpty())><x-icon name="file" />CSV</button><button type="button" class="btn btn-p" wire:click="download('xlsx')" @disabled($invoices->isEmpty())><x-icon name="download" />Excel</button>
    </div></section>
    <x-card title="Invoices in this export" :sub="$invoices->count() . ' invoice(s) · ' . Money::format($total, 'USD')" :flush="true">
        @if ($invoices->isEmpty())<x-empty icon="file" title="Nothing to export" text="No approved or paid invoice in this period is waiting. Tick the box to export earlier ones again." />
        @else<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Date</th><th>Document</th><th>Supplier</th><th>Order</th><th>Status</th><th class="num">Amount</th><th>Exported before</th></tr></thead><tbody>
            @foreach ($invoices as $i)<tr><td style="white-space:nowrap">{{ $i->issued_on->format('j M Y') }}</td><td class="mono">{{ $i->number }}</td><td class="tmain"><div class="t1">{{ $i->supplier->name }}</div><div class="t2 mono">{{ $i->supplier_ref }}</div></td><td class="mono">{{ $i->order->number }}</td><td><x-badge :status="$i->status === 'paid' ? 'ok' : 'info'" :label="$i->statusLabel()" /></td><td class="num mono b">{{ Money::format($i->total_cents, $i->currency) }}</td><td class="muted">{{ $i->exported_at?->format('j M Y, H:i') ?? 'No' }}</td></tr>@endforeach
        </tbody></table></div>@endif
    </x-card>
    <h3 class="lbl" style="margin:22px 0 10px">Data sets for your ERP</h3>
    <div class="grid g-2">
        @foreach (\App\Livewire\Accounting::SETS as $key => [$title, $text, $cols])
            <x-card :title="$title">
                <p class="muted" style="margin-bottom:10px">{{ $text }}</p>
                <div class="row wrap" style="gap:6px;margin-bottom:14px">@foreach ($cols as $c)<span class="tag">{{ $c }}</span>@endforeach</div>
                <div class="row wrap" style="gap:8px">
                    <button type="button" class="btn" wire:click="exportSet('{{ $key }}', 'csv')" wire:loading.attr="disabled" wire:target="exportSet('{{ $key }}', 'csv')"><x-icon name="download" />Download CSV</button>
                    <button type="button" class="btn" wire:click="exportSet('{{ $key }}', 'json')" wire:loading.attr="disabled" wire:target="exportSet('{{ $key }}', 'json')"><x-icon name="download" />Download JSON</button>
                </div>
            </x-card>
        @endforeach
        <x-card title="Export history" sub="Every download is recorded" :flush="true">
            @if ($history->isEmpty())<div class="card-b faint">No exports yet.</div>
            @else<ul class="list">@foreach ($history as $h)<li data-plain><div class="grow"><div>{{ $h->action }}</div><div class="muted sm">{{ $h->actor_name }} · {{ $h->created_at->format('j M Y, H:i') }}</div></div></li>@endforeach</ul>@endif
        </x-card>
    </div>
</div>
