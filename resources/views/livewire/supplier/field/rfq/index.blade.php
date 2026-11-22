<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __('Assigned RFQs') }}</h1><p class="ph-d">{{ __('Your supplier-assigned field work and assessment progress.') }}</p></div></header>
    <section class="card"><div class="dt">
        <div class="dt-top">
            <label class="dt-search"><x-source.icon name="search" :size="16" /><span class="sr">{{ __('Search assignments') }}</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search RFQ title') }}"></label>
            <div class="dt-tools"><label class="sr" for="assignment-filter">{{ __('Status') }}</label>
                <select class="select" id="assignment-filter" wire:model.live="statusFilter">
                    <option value="">{{ __('All statuses') }}</option><option value="pending">{{ __('Pending') }}</option>
                    <option value="in_progress">{{ __('In progress') }}</option><option value="done">{{ __('Completed') }}</option>
                </select>
            </div>
        </div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('RFQ') }}</th><th>{{ __('Buyer') }}</th><th>{{ __('Assigned by') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Action') }}</th></tr></thead>
            <tbody>
            @forelse($assignments as $assignment)
                <tr wire:key="field-assignment-{{ $assignment->id }}">
                    <td><div class="t1">{{ $assignment->request?->title ?? __('Unavailable request') }}</div><div class="t2 mono">#{{ $assignment->request_id }}</div></td>
                    <td class="sm">{{ $assignment->request?->buyer?->name ?? '—' }}</td>
                    <td class="sm">{{ $assignment->assignedBy?->name ?? '—' }}</td>
                    <td class="mono sm">{{ $assignment->request?->deadline?->format('d M Y') ?? '—' }}</td>
                    <td><span class="badge {{ $assignment->status === 'done' ? 'b-ok' : ($assignment->status === 'in_progress' ? 'b-info' : 'b-warn') }}">{{ ucfirst(str_replace('_',' ',$assignment->status)) }}</span></td>
                    <td><a class="btn btn-sm" href="{{ route('supplier.field.rfq.show', $assignment->request) }}">{{ __('Open') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><h3>{{ __('No assigned RFQs') }}</h3><p>{{ __('Your supplier account can assign field work to you.') }}</p></div></td></tr>
            @endforelse
            </tbody>
        </table></div><div class="dt-foot">{{ $assignments->links() }}</div>
    </div></section>
</div>