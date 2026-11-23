@php
    use App\Support\Money;
    $labels = ['pending' => ['info', 'New invitation'], 'accepted' => ['ok', 'Accepted'], 'public' => ['info', 'Public tender'], 'withdrawn' => ['', 'Withdrawn'], 'sent' => ['ok', 'Submitted'], 'review' => ['warn', 'Under review'], 'won' => ['ok', 'Won'], 'lost' => ['bad', 'Not selected'], 'declined' => ['bad', 'Declined'], 'missed' => ['', 'Closed without our quote'], 'cancelled' => ['bad', 'Cancelled by buyer']];
    $canAct = auth()->user()->allowed('quotes', 'E') && $supplier->isActive();
@endphp
<div>
    <x-ph title="Invitations & quotations" desc="Accept or decline each invitation, then send your quotation. You can revise or withdraw it until the deadline." :crumbs="[['Invitations & quotes']]" />
    @unless ($supplier->isActive())
        <div style="margin-bottom:16px"><x-note kind="warn"><b>Your supplier account is {{ mb_strtolower($supplier->statusLabel()) }}.</b> You can read requests but cannot respond to them yet.</x-note></div>
    @endunless
    <div class="tabs" role="tablist">
        @foreach (['open' => 'Requiring response', 'sent' => 'Submitted', 'closed' => 'Declined & closed'] as $k => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}" @class(['tab', 'on' => $tab === $k]) wire:click="$set('tab', '{{ $k }}')">{{ $label }}<span class="n">{{ $groups[$k]->count() }}</span></button>
        @endforeach
    </div>
    <x-card :flush="true">
        @if ($rows->isEmpty())
            <x-empty icon="inbox" :title="$tab === 'open' ? 'Nothing waiting for you' : 'Nothing here yet'" :text="$tab === 'open' ? 'You have answered every open invitation.' : 'Requests appear here as they progress.'" />
        @else
            <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Request</th><th>Buyer</th><th>Deadline</th><th>Status</th><th class="num">Our total</th><th></th></tr></thead><tbody>
                @foreach ($rows as $r)
                    @php $q = $r->quotes->first(); $s = $r->my_state; @endphp
                    <tr wire:key="{{ $r->id }}">
                        <td><a href="{{ route('quotes.form', $r) }}" wire:navigate style="color:inherit"><div class="t1">{{ $r->title }}</div><div class="t2"><span class="mono">{{ $r->number }}</span> · {{ $r->items->count() }} item(s)</div></a></td>
                        <td>{{ $r->buyer->name }}</td>
                        <td>{{ $r->deadline_at?->format('j M Y, H:i') }}<div class="t2">{{ $r->status === 'open' ? $r->deadline_at->diffForHumans() : '' }}</div></td>
                        <td><x-badge :status="$labels[$s][0]" :label="$labels[$s][1]" /></td>
                        <td class="num mono">{{ $q && in_array($q->status, ['submitted', 'won', 'lost']) ? Money::format($q->total_cents, $q->currency) : '-' }}</td>
                        <td class="num"><div class="row" style="justify-content:flex-end">
                            @if ($canAct && $r->isOpenForQuotes())
                                @if ($s === 'pending')<button type="button" class="btn btn-sm btn-p" wire:click="accept('{{ $r->number }}')">Accept</button><button type="button" class="btn btn-sm btn-d" wire:click="askDecline('{{ $r->number }}')">Decline</button>
                                @elseif (in_array($s, ['accepted', 'public', 'withdrawn']))<a class="btn btn-sm btn-p" href="{{ route('quotes.form', $r) }}" wire:navigate>Prepare quote</a>@if ($s === 'accepted')<button type="button" class="btn btn-sm btn-g" wire:click="askDecline('{{ $r->number }}')">Decline</button>@endif
                                @elseif ($s === 'sent')<a class="btn btn-sm" href="{{ route('quotes.form', $r) }}" wire:navigate>Revise</a><button type="button" class="btn btn-sm btn-d" wire:click="withdraw('{{ $r->number }}')" wire:confirm="Withdraw your quotation on {{ $r->number }}? The buyer will no longer see it.">Withdraw</button>
                                @elseif ($s === 'declined')<button type="button" class="btn btn-sm" wire:click="accept('{{ $r->number }}')">Rejoin</button>@endif
                            @else<a class="btn btn-sm btn-g" href="{{ route('quotes.form', $r) }}" wire:navigate>View</a>@endif
                        </div></td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </x-card>
    @if ($modal === 'decline')
        <x-modal title="Decline invitation">
            <x-field label="Reason" for="dreason" :opt="true" help="Shared with the buyer. It helps them invite you to the right requests."><textarea id="dreason" class="textarea" rows="3" wire:model="reason" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" wire:click="close">Cancel</button><button type="button" class="btn btn-d solid" wire:click="decline">Decline invitation</button></x-slot:foot>
        </x-modal>
    @endif
</div>
