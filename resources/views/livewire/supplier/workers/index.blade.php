<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __('Field workers') }}</h1><p class="ph-d">{{ __('Manage evaluators assigned to your supplier account.') }}</p></div>
        <div class="ph-a"><livewire:supplier.workers.create @created="$refresh" /></div></header>
    <section class="card"><div class="dt"><div class="dt-top">
        <span class="sm muted">{{ $workers->total() }} {{ __('workers') }}</span>
        <div class="dt-tools"><label for="worker-pagesize" class="sr">{{ __('Rows') }}</label><select id="worker-pagesize" class="select" wire:model.live="perPage"><option value="10">10</option><option value="20">20</option><option value="50">50</option></select></div>
    </div>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>{{ __('Worker') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Assigned RFQs') }}</th><th>{{ __('Created') }}</th><th>{{ __('Actions') }}</th></tr></thead>
        <tbody>
        @forelse($workers as $worker)
            <tr wire:key="supplier-worker-{{ $worker->id }}">
                <td><a class="t1 link" href="{{ route('supplier.workers.show', $worker) }}">{{ $worker->name }}</a><div class="t2">{{ $worker->email }}</div></td>
                <td><span class="badge {{ $worker->is_active ? 'b-ok' : 'b-bad' }}">{{ $worker->is_active ? __('Active') : __('Inactive') }}</span></td>
                <td class="num mono">{{ $worker->assignedRfqs()->count() }}</td>
                <td class="mono sm">{{ $worker->created_at?->format('d M Y') ?? '—' }}</td>
                <td><div class="row"><a href="{{ route('supplier.workers.show', $worker) }}" class="btn btn-sm">{{ __('Manage') }}</a>
                    <button type="button" class="btn btn-sm" wire:click="$dispatch('supplier::workers::load', { worker: '{{ $worker->id }}' })">{{ __('Edit') }}</button></div></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty">{{ __('No field workers found') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="dt-foot">{{ $workers->links() }}</div>
    </div></section>
    <livewire:supplier.workers.update @updated="$refresh" @deleted="$refresh" />
</div>