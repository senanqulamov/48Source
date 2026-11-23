@php use App\Support\Money; $live = $a->isLive(); $best = $standing ? reset($standing) : null; @endphp
<div @if ($live) wire:poll.5s @endif>
    <x-ph title="Reverse auction" :crumbs="$sourcing ? [['RFQs', route('rfqs.index')], [$rfq->number, route('rfqs.show', $rfq)], ['Auction']] : [['Invitations & quotes', route('quotes.invitations')], [$rfq->number, route('quotes.form', $rfq)], ['Auction']]">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $rfq->number }}</span><x-badge :status="$live ? 'warn' : 'ok'" :label="$live ? 'Live' : 'Ended'" /></div></x-slot:pre>
        @if ($sourcing)@if ($live)@perm('quotes', 'A')<button type="button" class="btn btn-d" wire:click="endNow" wire:confirm="End the auction now? The current totals become final.">End now</button>@endperm @endif<a class="btn {{ $live ? '' : 'btn-p' }}" href="{{ route('rfqs.compare', $rfq) }}" wire:navigate><x-icon name="cols" />Compare quotations</a>@endif
    </x-ph>
    <p class="ph-d" style="margin:-10px 0 16px">{{ $rfq->title }}</p>
    <div class="strip">
        <div class="strip-i"><span class="strip-l">{{ $live ? 'Time left' : 'Ended' }}</span><span class="strip-v" data-done="1" @if ($live) x-data="{ end: {{ $a->ends_at->getTimestampMs() }}, t: '' , tick() { const s = Math.max(0, Math.floor((this.end - Date.now()) / 1000)); this.t = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); } }" x-init="tick(); setInterval(() => tick(), 1000)" x-text="t" wire:key="clock-{{ $a->ends_at->timestamp }}" @endif>{{ $live ? '' : $a->ends_at->format('H:i') }}</span><span class="strip-s">{{ $live ? 'ends ' . $a->ends_at->format('H:i:s') : $a->ends_at->format('j M Y') }}&nbsp;</span></div>
        @if ($sourcing)
            <div class="strip-i"><span class="strip-l">Best total</span><span class="strip-v" data-done="1" style="font-size:26px">{{ $best ? Money::format($best['total'], $rfq->currency, false) : '-' }}</span><span class="strip-s">{{ $best['name'] ?? '' }}&nbsp;</span></div>
            <div class="strip-i"><span class="strip-l">Saved against opening</span><span class="strip-v" data-done="1" style="font-size:26px">{{ $best ? Money::format(max(0, collect($standing)->min('start') - $best['total']), $rfq->currency, false) : '-' }}</span><span class="strip-s">against the lowest opening quotation&nbsp;</span></div>
            <div class="strip-i"><span class="strip-l">Bids</span><span class="strip-v">{{ collect($standing)->sum('bids') }}</span><span class="strip-s">{{ count($standing) }} suppliers&nbsp;</span></div>
        @else
            <div class="strip-i"><span class="strip-l">Your rank</span><span class="strip-v" data-done="1">{{ $rank ? $rank . ' of ' . count($standing) : '-' }}</span><span class="strip-s {{ $rank === 1 ? '' : 'warn' }}">{{ $rank === 1 ? 'you are leading' : ($rank ? 'you are not leading' : 'not taking part') }}&nbsp;</span></div>
            <div class="strip-i"><span class="strip-l">Your total</span><span class="strip-v" data-done="1" style="font-size:26px">{{ $own ? Money::format($own['total'], $rfq->currency) : '-' }}</span><span class="strip-s">{{ $own ? 'opened at ' . Money::format($own['start'], $rfq->currency, false) : '' }}&nbsp;</span></div>
            <div class="strip-i"><span class="strip-l">Your bids</span><span class="strip-v">{{ $own['bids'] ?? 0 }}</span><span class="strip-s">step at least {{ rtrim(rtrim(number_format($a->min_step_bp / 100, 2), '0'), '.') }}%&nbsp;</span></div>
        @endif
    </div>
    <div class="grid g-2-1">
        @if ($sourcing)
            <x-card title="Standing" sub="Best total first" :flush="true"><div class="tbl-wrap"><table class="tbl" style="min-width:0"><thead><tr><th style="width:50px">Rank</th><th>Supplier</th><th class="num">Opening</th><th class="num">Current</th><th class="num">Change</th><th class="num">Bids</th></tr></thead><tbody>
                @php $n = 0; @endphp
                @foreach ($standing as $row)@php $n++; @endphp<tr><td class="mono b">{{ $n }}</td><td class="t1">{{ $row['name'] }}</td><td class="num mono">{{ Money::format($row['start'], $rfq->currency) }}</td><td class="num mono b">{{ Money::format($row['total'], $rfq->currency) }}</td><td class="num mono" style="color:var(--ok)">{{ $row['start'] > $row['total'] ? '-' . number_format(intdiv(($row['start'] - $row['total']) * 1000, $row['start']) / 10, 1) . '%' : '0%' }}</td><td class="num mono">{{ $row['bids'] }}</td></tr>@endforeach
            </tbody></table></div></x-card>
        @else
            <x-card title="Lower your total" :sub="$live ? 'You see your own rank, never the other prices' : 'The auction has ended'">
                @if ($live && $own)
                    <x-field label="New total for the whole quotation" for="au-bid" :help="'Must be ' . Money::format($ceiling, $rfq->currency) . ' or lower. Your line prices are reduced in proportion when the auction ends.'"><input id="au-bid" class="input mono" inputmode="decimal" wire:model="amount" placeholder="{{ Money::input($ceiling) }}" wire:keydown.enter="bid"></x-field>
                    <div class="row" style="margin-top:12px"><span class="sp"></span>@perm('quotes', 'E')<button type="button" class="btn btn-p" wire:click="bid" wire:loading.attr="disabled"><x-icon name="pulse" />Place bid</button>@endperm</div>
                @elseif ($own)<x-note :kind="$rank === 1 ? 'ok' : 'info'">Your final total of {{ Money::format($own['total'], $rfq->currency) }} is recorded on your quotation. You finished in position {{ $rank }} of {{ count($standing) }}. The buyer decides the award.</x-note>
                @else<x-note kind="info">Your company did not take part in this auction.</x-note>@endif
            </x-card>
        @endif
        <x-card :title="$sourcing ? 'Latest bids' : 'Your bids'" :flush="true">
            @if ($bids->isEmpty())<div class="card-b faint">No bids yet.</div>
            @else<ul class="list">@foreach ($bids as $b)<li><div class="grow"><div class="t1 mono">{{ Money::format($b->total_cents, $rfq->currency) }}</div><div class="muted sm">{{ $sourcing ? $b->supplier->name . ' · ' : '' }}{{ $b->created_at->format('H:i:s') }}</div></div></li>@endforeach</ul>@endif
        </x-card>
    </div>
</div>
