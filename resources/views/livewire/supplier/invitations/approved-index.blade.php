<div class="stack">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ __('Invitations & RFQs') }}</h1>
            <p class="ph-d">{{ __('Review buyer invitations and respond before their deadlines.') }}</p>
        </div>
        <div class="ph-a"><a class="btn" href="{{ route('supplier.quotes.index') }}">{{ __('My quotations') }}</a></div>
    </header>
    <section class="card" aria-label="Supplier RFQ invitations">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search">
                    <x-source.icon name="search" :size="16" />
                    <span class="sr">{{ __('Search invitations') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by request title') }}" />
                </label>
                <div class="dt-tools">
                    <label class="sm muted" for="invitation-status">{{ __('Status') }}</label>
                    <select id="invitation-status" class="select" wire:model.live="statusFilter">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="pending">{{ __('New invitation') }}</option>
                        <option value="accepted">{{ __('Accepted') }}</option>
                        <option value="declined">{{ __('Declined') }}</option>
                        <option value="quoted">{{ __('Quoted') }}</option>
                    </select>
                </div>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Request') }}</th>
                        <th>{{ __('Buyer') }}</th>
                        <th>{{ __('Deadline') }}</th>
                        <th>{{ __('Invitation') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr></thead>
                    <tbody>
                    @forelse($this->rows as $invitation)
                        <tr wire:key="invitation-{{ $invitation->id }}">
                            <td class="mono">#{{ $invitation->request_id }}</td>
                            <td><div class="t1">{{ $invitation->request?->title ?? __('Request not found') }}</div>
                                <div class="t2">{{ $invitation->request?->request_type === 'public' ? __('Public tender') : __('Internal request') }}</div>
                            </td>
                            <td class="sm">{{ $invitation->request?->buyer?->name ?? '—' }}</td>
                            <td class="mono sm">{{ $invitation->request?->deadline?->format('d M Y') ?? '—' }}</td>
                            <td><span class="badge {{ $invitation->status === 'accepted' ? 'b-ok' : ($invitation->status === 'pending' ? 'b-warn' : '') }}">{{ ucfirst($invitation->status) }}</span></td>
                            <td>
                                @if($invitation->status === 'pending' && $invitation->request?->status === 'open')
                                    <div class="row">
                                        <button class="btn btn-sm btn-p" type="button" wire:click="acceptInvitation({{ $invitation->id }})">{{ __('Accept') }}</button>
                                        <button class="btn btn-sm" type="button" wire:click="declineInvitation({{ $invitation->id }})" wire:confirm="{{ __('Decline this invitation?') }}">{{ __('Decline') }}</button>
                                    </div>
                                @elseif($invitation->status === 'accepted')
                                    <span class="sm muted">{{ __('Ready for quotation') }}</span>
                                @else
                                    <span class="sm muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><h3>{{ __('No invitations found') }}</h3><p>{{ __('Try another search or filter.') }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="dt-foot">{{ $this->rows->links() }}</div>
        </div>
    </section>
</div>
