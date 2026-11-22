@props(['rows', 'title' => 'Activity log', 'types' => []])
<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __($title) }}</h1>
        <p class="ph-d">{{ __('Review recorded activity, updates, and important system actions.') }}</p></div></header>
    <section class="card">
        <div class="dt"><div class="dt-top">
            <label class="dt-search"><x-source.icon name="search" :size="16" /><span class="sr">{{ __('Search activity') }}</span>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search action or message') }}"></label>
            <div class="dt-tools">
                <label for="activity-type" class="sr">{{ __('Type') }}</label>
                <select id="activity-type" class="select" wire:model.live="typeFilter">
                    <option value="">{{ __('All types') }}</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}">{{ ucfirst(str_replace('_',' ',$type)) }}</option>
                    @endforeach
                </select>
                <label for="activity-count" class="sr">{{ __('Rows') }}</label>
                <select id="activity-count" class="select" wire:model.live="quantity">
                    <option value="10">10</option><option value="20">20</option><option value="50">50</option>
                </select>
            </div>
        </div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Type') }}</th><th>{{ __('Action') }}</th><th>{{ __('Message') }}</th></tr></thead>
            <tbody>
            @forelse($rows as $entry)
                <tr wire:key="activity-{{ $entry->id }}">
                    <td class="mono sm">{{ $entry->created_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td><span class="badge {{ $entry->type === 'error' ? 'b-bad' : ($entry->type === 'create' ? 'b-ok' : '') }}">{{ ucfirst(str_replace('_',' ',$entry->type ?: 'event')) }}</span></td>
                    <td class="sm">{{ $entry->action ?: '—' }}</td>
                    <td class="sm">{{ \Illuminate\Support\Str::limit($entry->message, 180) }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty"><h3>{{ __('No activity found') }}</h3><p>{{ __('Try changing the search or type filter.') }}</p></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="dt-foot">{{ $rows->links() }}</div>
        </div>
    </section>
</div>