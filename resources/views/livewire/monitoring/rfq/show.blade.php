<div class="stack">
    <div class="row wrap">
        <a class="btn" href="{{ route('monitoring.rfq.index') }}">{{ __('Back to monitoring') }}</a>
        <a class="btn" href="{{ route('export.rfq.pdf', $request) }}" target="_blank" rel="noopener">{{ __('Export RFQ PDF') }}</a>
        @if(\Illuminate\Support\Facades\Route::has('export.rfq.excel'))
            <a class="btn" href="{{ route('export.rfq.excel',$request) }}">{{ __('Export Excel') }}</a>
        @endif
    </div>
    <x-source.rfq-detail :request="$request" />
    <section class="card">
        <div class="card-h"><h2 class="card-t">{{ __('Administration & invitations') }}</h2></div>
        <div class="card-b stack">
            <div class="row wrap">
                <label for="monitor-rfq-status" class="sm">{{ __('RFQ status') }}</label>
                <select class="select" id="monitor-rfq-status" wire:model.live="statusValue">
                    @foreach($availableStatuses as $key => $label)
                        <option value="{{ $key }}">{{ __($label) }}</option>
                    @endforeach
                </select>
                <span class="sp"></span>
                <button type="button" class="btn btn-p" wire:click="openInviteModal">{{ __('Invite suppliers') }}</button>
            </div>
            @if($showInviteModal)
                <div class="card">
                    <div class="card-h"><h3 class="card-t">{{ __('Select eligible suppliers') }}</h3></div>
                    <div class="card-b stack">
                        @forelse($this->availableSuppliers as $supplier)
                            <label class="row" style="cursor:pointer"><input class="chk" type="checkbox" value="{{ $supplier->id }}" wire:model="selectedSuppliers" />
                                <span>{{ $supplier->company_name ?: $supplier->name }} <span class="muted sm">{{ $supplier->email }}</span></span>
                            </label>
                        @empty
                            <div class="empty">{{ __('All eligible suppliers have already been invited.') }}</div>
                        @endforelse
                        <div class="row wrap">
                            <button type="button" class="btn" wire:click="closeInviteModal">{{ __('Cancel') }}</button>
                            @if($this->availableSuppliers->isNotEmpty())
                                <button type="button" class="btn btn-p" wire:click="inviteSuppliers">{{ __('Send invitations') }}</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Status') }}</th><th>{{ __('Invited') }}</th><th>{{ __('Responded') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                @forelse($request->supplierInvitations as $invitation)
                    <tr wire:key="monitor-invitation-{{ $invitation->id }}">
                        <td class="t1">{{ $invitation->supplier?->company_name ?: $invitation->supplier?->name ?: __('Unknown') }}</td>
                        <td><span class="badge">{{ ucfirst($invitation->status) }}</span></td>
                        <td class="mono sm">{{ $invitation->sent_at?->format('d M Y') ?? '—' }}</td>
                        <td class="mono sm">{{ $invitation->responded_at?->format('d M Y') ?? '—' }}</td>
                        <td><button class="btn btn-sm btn-d" type="button" wire:click="deleteInvitation({{ $invitation->id }})" wire:confirm="{{ __('Remove this invitation?') }}">{{ __('Remove') }}</button></td>
                    </tr>
                @empty<tr><td colspan="5"><div class="empty">{{ __('No invitations sent.') }}</div></td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </section>
    <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Quote review') }}</h2></div>
        <div class="card-b stack">
            <div class="note warn">{{ __('The required audited award decision and purchase-order generation are not active. Award controls are deliberately not exposed until those operations are implemented.') }}</div>
            @foreach($request->quotes as $quote)
                <div class="row wrap" style="justify-content:space-between">
                    <span class="t1">{{ $quote->supplier?->company_name ?: $quote->supplier?->name ?: __('Supplier') }} · #{{ $quote->id }}</span>
                    <span class="badge">{{ ucfirst($quote->status) }}</span>
                    <span class="mono">{{ $quote->currency }} {{ number_format((float)($quote->total_amount ?? $quote->total_price),2) }}</span>
                    @if($quote->status === 'submitted')
                        <button type="button" class="btn btn-sm btn-d" wire:click="confirmRejectQuote({{ $quote->id }})">{{ __('Reject') }}</button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
    <livewire:monitoring.rfq.update @updated="$refresh" />
</div>