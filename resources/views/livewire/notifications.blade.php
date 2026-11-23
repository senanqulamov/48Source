<div>
    <x-ph title="Notifications" desc="Everything that needs your attention, newest first. Click one to go straight to the record." :crumbs="[['Notifications']]">
        @if ($unread)<button class="btn" type="button" wire:click="readAll"><x-icon name="check" />Mark all as read</button>@endif
    </x-ph>
    <div class="seg" role="group" style="margin-bottom:14px">
        <button type="button" @class(['on' => ! $unreadOnly]) wire:click="$set('unreadOnly', false)">All</button>
        <button type="button" @class(['on' => $unreadOnly]) wire:click="$set('unreadOnly', true)">Unread<span class="n">{{ $unread }}</span></button>
    </div>
    <x-card :flush="true">
        @if ($rows->isEmpty())
            <x-empty icon="bell" :title="$unreadOnly ? 'No unread notifications' : 'No notifications yet'" text="You are told here when a request, quotation or order needs you." />
        @else
            <ul class="list">
                @foreach ($rows as $n)
                    <li wire:key="{{ $n->id }}"><button type="button" class="row grow" wire:click="openNotice('{{ $n->id }}')" style="all:unset;cursor:pointer;display:flex;gap:12px;align-items:center;flex:1">
                        <span class="dot-s" style="width:8px;height:8px;border-radius:50%;flex:none;background:{{ $n->read_at ? 'transparent' : 'var(--primary)' }}"></span>
                        <div class="grow" style="min-width:0"><div @class(['t1' => ! $n->read_at])>{{ $n->data['text'] ?? '' }}</div><div class="muted sm"><span class="tag">{{ $n->data['area'] ?? 'General' }}</span> {{ $n->created_at->diffForHumans() }}</div></div>
                        <x-icon name="right" :size="14" /></button></li>
                @endforeach
            </ul>
            <div class="dt-foot"><span class="muted sm">{{ $rows->total() }} in total</span><span class="sp"></span>{{ $rows->links('livewire.simple-pager') }}</div>
        @endif
    </x-card>
</div>
