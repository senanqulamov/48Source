<div>
    <x-ph title="Messages" :desc="$sourcing ? 'Conversations with suppliers. Everything said here stays on record.' : 'Conversations with the buyer.'" :crumbs="[['Messages']]">
        <button type="button" class="btn btn-p" wire:click="open('new')"><x-icon name="plus" />New message</button>
    </x-ph>
    <div class="grid" style="grid-template-columns:minmax(260px,360px) minmax(0,1fr);align-items:start">
        <x-card :flush="true">
            <div class="toolbar"><div class="dt-search block" style="flex:1"><x-icon name="search" /><input type="search" wire:model.live.debounce.300ms="find" placeholder="Search subject or supplier" aria-label="Search conversations"></div></div>
            @if ($list->isEmpty())<x-empty icon="mail" title="No conversations" text="Start one with the button above." />
            @else
                <ul class="list" style="max-height:62vh;overflow:auto">
                    @foreach ($list as $cv)
                        @php $unread = (int) $cv->last_other_id > (int) ($cv->reads->first()?->message_id ?? 0); @endphp
                        <li wire:key="cv-{{ $cv->id }}" class="click" style="cursor:pointer;{{ $c === $cv->id ? 'background:var(--surface-2)' : '' }}" wire:click="pick({{ $cv->id }})" tabindex="0" wire:keydown.enter="pick({{ $cv->id }})">
                            <div class="grow" style="min-width:0"><div class="t1 trunc" @if ($unread) style="font-weight:700" @endif>{{ $cv->subject }}</div><div class="muted sm trunc">{{ $sourcing ? $cv->supplier->name : 'Buyer' }} · {{ $cv->messages_count }} message{{ $cv->messages_count === 1 ? '' : 's' }}</div></div>
                            <div style="text-align:right;flex:none"><div class="muted xs">{{ $cv->last_message_at?->diffForHumans(['short' => true, 'parts' => 1]) }}</div>@if ($unread)<span class="badge b-info">New</span>@endif</div></li>
                    @endforeach
                </ul>
            @endif
        </x-card>
        @if ($open)
            <x-card :title="$open->subject" :sub="$sourcing ? $open->supplier->name : 'With the buyer'" :flush="true">
                <div style="max-height:52vh;overflow:auto;padding:16px 18px;display:grid;gap:12px" x-data x-init="$el.scrollTop = $el.scrollHeight" wire:key="thread-{{ $open->id }}-{{ $open->messages->count() }}">
                    @foreach ($open->messages as $m)
                        @php $me = $m->user_id === auth()->id(); @endphp
                        <div style="display:flex;{{ $me ? 'justify-content:flex-end' : '' }}"><div style="max-width:min(560px,86%);padding:10px 14px;border-radius:14px;{{ $me ? 'background:var(--ink);color:#fff' : 'background:var(--surface-2);border:1px solid var(--line)' }}">
                            <div style="font-size:12px;opacity:.7;margin-bottom:2px">{{ $me ? 'You' : $m->user->name }} · {{ $m->created_at->format('j M, H:i') }}</div><div style="white-space:pre-line">{{ $m->body }}</div></div></div>
                    @endforeach
                </div>
                <div class="card-b" style="border-top:1px solid var(--line)"><div class="row" style="align-items:flex-end"><div class="field grow"><label for="msg-b" class="sr">Message</label><textarea id="msg-b" class="textarea" rows="2" wire:model="body" placeholder="Write a message" x-on:keydown.ctrl.enter="$wire.send()"></textarea></div><button type="button" class="btn btn-p" wire:click="send" wire:loading.attr="disabled"><x-icon name="send" />Send</button></div><span class="help">Ctrl + Enter sends.</span></div>
            </x-card>
        @else
            <x-card><x-empty icon="mail" title="Choose a conversation" text="Pick one on the left to read it and reply." /></x-card>
        @endif
    </div>
    @if ($modal === 'new')
        <x-modal title="New message"><div class="stack" style="gap:14px">
            @if ($sourcing)<x-field label="Supplier" for="nm-s"><select id="nm-s" class="select" wire:model="new.supplier_id"><option value="">Choose a supplier</option>@foreach ($suppliers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></x-field>@endif
            <x-field label="Subject" for="nm-t"><input id="nm-t" class="input" wire:model="new.subject"></x-field><x-field label="Message" for="nm-b"><textarea id="nm-b" class="textarea" rows="4" wire:model="new.body"></textarea></x-field></div>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-p" wire:click="start" wire:loading.attr="disabled">Send</button></x-slot:foot></x-modal>
    @endif
</div>
