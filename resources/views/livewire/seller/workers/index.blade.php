<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __('Market workers') }}</h1><p class="ph-d">{{ __('Manage employees assigned to your markets.') }}</p></div>
        <div class="ph-a"><button type="button" class="btn btn-p" wire:click="$dispatch('seller::workers::create::open')">+ {{ __('Create worker') }}</button></div></header>
    <section class="card"><div class="dt"><div class="dt-top"><span class="sm muted">{{ $workers->total() }} {{ __('workers') }}</span>
        <div class="dt-tools"><label for="seller-worker-pagesize" class="sr">{{ __('Rows') }}</label><select id="seller-worker-pagesize" class="select" wire:model.live="perPage"><option value="10">10</option><option value="20">20</option><option value="50">50</option></select></div>
    </div>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>{{ __('Worker') }}</th><th>{{ __('Markets') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th>{{ __('Action') }}</th></tr></thead>
        <tbody>
        @forelse($workers as $worker)
            <tr wire:key="seller-worker-{{ $worker->id }}">
                <td><div class="t1">{{ $worker->name }}</div><div class="t2">{{ $worker->email }}</div></td>
                <td><div class="row wrap">@foreach($worker->workerMarkets as $market)<span class="tag">{{ $market->name }}</span>@endforeach</div></td>
                <td><span class="badge {{ $worker->is_active ? 'b-ok' : 'b-bad' }}">{{ $worker->is_active ? __('Active') : __('Inactive') }}</span></td>
                <td class="mono sm">{{ $worker->created_at?->format('d M Y') ?? '—' }}</td>
                <td><button type="button" class="btn btn-sm" wire:click="$dispatch('seller::workers::load', { worker: '{{ $worker->id }}' })">{{ __('Edit') }}</button></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty">{{ __('No market workers yet') }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div><div class="dt-foot">{{ $workers->links() }}</div>
    </div></section>
    <livewire:seller.workers.create @created="$refresh" />
    <livewire:seller.workers.update @updated="$refresh" @deleted="$refresh" />
</div>