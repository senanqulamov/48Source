<div class="stack">
    <header class="ph"><div><h1 class="ph-t">{{ __('Notifications') }}</h1>
        <p class="ph-d">{{ __('Read updates about RFQs, quotes, supplier activity and orders.') }}</p></div>
        <div class="ph-a"><button class="btn" type="button" wire:click="markAllRead">{{ __('Mark all as read') }}</button></div>
    </header>
    <section class="card">
        <div class="dt">
            <div class="dt-top">
                <label class="dt-search"><x-source.icon name="search" :size="16"/>
                    <span class="sr">{{ __('Type') }}</span>
                    <select class="select" wire:model.live="type" aria-label="{{ __('Filter notifications by type') }}">
                        <option value="">{{ __('All notifications') }}</option>
                        @foreach($this->types as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach
                    </select>
                </label>
            </div>
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>{{ __('Notification') }}</th><th>{{ __('Received') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse($this->rows as $item)
                        <tr wire:key="notification-{{ $item->id }}">
                            <td>
                                <div class="t1">{{ class_basename($item->type) }}</div>
                                <div class="t2" style="white-space:pre-wrap">{{ json_encode($item->data, JSON_UNESCAPED_UNICODE) }}</div>
                            </td>
                            <td class="mono sm">{{ $item->created_at?->format('d M Y, H:i') ?? '—' }}</td>
                            <td><span class="badge {{ $item->read_at ? '' : 'b-info' }}">{{ $item->read_at ? __('Read') : __('Unread') }}</span></td>
                            <td>@unless($item->read_at)
                                <button class="btn btn-sm" type="button" wire:click="markRead('{{ $item->id }}')">{{ __('Mark as read') }}</button>
                            @endunless</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty"><h3>{{ __('No notifications') }}</h3></div></td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="dt-foot">{{ $this->rows->links() }}</div>
        </div>
    </section>
</div>