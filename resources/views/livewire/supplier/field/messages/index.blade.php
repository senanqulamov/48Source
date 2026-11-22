<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ __('Field worker messages') }}</h1>
            <p class="ph-d">{{ __('Direct conversation with your parent supplier.') }}</p></div>
    </header>
    <section class="card">
        <div class="card-h"><h2 class="card-t">{{ __('Conversation') }}</h2>
            <span class="card-s">{{ $supplier?->getDisplayName() ?? __('No supplier linked') }}</span></div>
        @if(! $supplier)
            <div class="card-b"><div class="empty"><h3>{{ __('No parent supplier linked') }}</h3><p>{{ __('Ask your administrator to assign your account to a supplier.') }}</p></div></div>
        @else
            <div class="card-b stack">
                <div class="row"><span class="avatar">{{ strtoupper(substr($supplier->name,0,1)) }}</span>
                    <div><strong class="sm">{{ $supplier->getDisplayName() }}</strong><div class="muted sm">{{ $supplier->email }}</div></div>
                </div>
                <div id="messages-container" class="stack" style="max-height:420px;overflow:auto;min-height:160px;padding:12px;background:var(--sunken);border-radius:var(--r)">
                    @forelse($messages as $message)
                        @php($mine = $message->sender_id === auth()->id())
                        <div class="card" style="padding:12px;max-width:80%;align-self:{{ $mine ? 'flex-end' : 'flex-start' }};{{ $mine ? 'border-color:var(--primary);' : '' }}">
                            @unless($mine)<strong class="sm">{{ $message->sender?->name ?? __('Supplier') }}</strong>@endunless
                            <p style="white-space:pre-wrap">{{ $message->body }}</p>
                            @if($message->rfq)<div class="muted sm">{{ __('Regarding') }}: {{ $message->rfq->title }}</div>@endif
                            <div class="muted xs">{{ $message->created_at?->format('d M, H:i') }}</div>
                        </div>
                    @empty
                        <div class="empty">{{ __('No messages yet. Start the conversation.') }}</div>
                    @endforelse
                </div>
                <form wire:submit="sendMessage" class="stack">
                    <div class="field"><label for="worker-message">{{ __('Message') }}</label>
                        <textarea id="worker-message" class="textarea" wire:model="newMessage" rows="3" maxlength="2000"
                            placeholder="{{ __('Write a message to your supplier') }}" required></textarea>
                        @error('newMessage')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div class="row" style="justify-content:flex-end"><button type="submit" class="btn btn-p" wire:loading.attr="disabled" wire:target="sendMessage">{{ __('Send message') }}</button></div>
                </form>
            </div>
        @endif
    </section>
</div>