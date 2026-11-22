<div class="stack">
    <header class="ph">
        <div><h1 class="ph-t">{{ __('Field worker workspace') }}</h1><p class="ph-d">{{ __('Keep your assigned field activities on track.') }}</p></div>
        <div class="ph-a"><a href="{{ route('supplier.field.rfq.index') }}" class="btn btn-p">{{ __('My assignments') }}</a></div>
    </header>

    <div class="strip">
        <a class="strip-i" href="{{ route('supplier.field.rfq.index') }}"><span class="strip-l">{{ __('Assigned RFQs') }}</span><span class="strip-v">{{ $assignedRfqs }}</span><span class="strip-s">{{ __('My assigned requests') }}</span></a>
        <div class="strip-i"><span class="strip-l">{{ __('Pending') }}</span><span class="strip-v">{{ $pendingRfqs }}</span><span class="strip-s">{{ __('Not started') }}</span></div>
        <div class="strip-i"><span class="strip-l">{{ __('In progress') }}</span><span class="strip-v">{{ $inProgressRfqs }}</span><span class="strip-s">{{ __('Work underway') }}</span></div>
        <div class="strip-i"><span class="strip-l">{{ __('Done') }}</span><span class="strip-v">{{ $doneRfqs }}</span><span class="strip-s">{{ __('Completed assignments') }}</span></div>
    </div>

    <div class="grid g-2-1">
        <section class="card">
            <div class="card-h"><h2 class="card-t">{{ __('My work today') }}</h2></div>
            <div class="card-b stack">
                @if($pendingRfqs + $inProgressRfqs)
                    <div class="note warn">{{ __('You have active assignments waiting for work.') }}</div>
                    <p class="muted">{{ __('Open your assigned RFQs to review the current work and update progress.') }}</p>
                @else
                    <div class="empty">
                        <h3>{{ __('No assignments currently need attention') }}</h3>
                        <p>{{ __('New assignments from your supplier account will appear here.') }}</p>
                    </div>
                @endif
                <a href="{{ route('supplier.field.rfq.index') }}" class="btn btn-p">{{ __('View assigned RFQs') }}</a>
            </div>
        </section>
        <aside class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Messages') }}</h2></div>
                <div class="card-b">
                    <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Unread messages') }}</span><b class="mono">{{ $unreadMessages }}</b></div>
                    <a href="{{ route('supplier.field.messages.index') }}" class="btn" style="margin-top:12px">{{ __('Open messages') }}</a>
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Activity') }}</h2></div>
                <div class="card-b"><div class="row" style="justify-content:space-between"><span class="muted">{{ __('Activity events in the last 7 days') }}</span><b class="mono">{{ $recentLogs }}</b></div></div>
            </section>
        </aside>
    </div>
</div>
