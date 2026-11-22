<div class="stack">
    <header class="ph">
        <div>
            <h1 class="ph-t">{{ __('Supplier workspace') }}</h1>
            <p class="ph-d">{{ __('Invitations, quotations and field work that need your attention.') }}</p>
        </div>
        <div class="ph-a">
            <a class="btn btn-p" href="{{ route('supplier.invitations.index') }}">{{ __('View invitations') }}</a>
        </div>
    </header>

    <div class="strip">
        <a class="strip-i" href="{{ route('supplier.invitations.index') }}">
            <span class="strip-l">{{ __('New invitations') }}</span>
            <span class="strip-v">{{ $pendingInvitations }}</span>
            <span class="strip-s">{{ __('Awaiting your response') }}</span>
        </a>
        <a class="strip-i" href="{{ route('supplier.quotes.index') }}">
            <span class="strip-l">{{ __('Active quotations') }}</span>
            <span class="strip-v">{{ $activeQuotes }}</span>
            <span class="strip-s">{{ __('Draft or submitted') }}</span>
        </a>
        <div class="strip-i">
            <span class="strip-l">{{ __('Field assignments') }}</span>
            <span class="strip-v">{{ $workerAssignments }}</span>
            <span class="strip-s">{{ __('Assigned by your company') }}</span>
        </div>
        <a class="strip-i" href="{{ route('supplier.messages.index') }}">
            <span class="strip-l">{{ __('Unread messages') }}</span>
            <span class="strip-v">{{ $unreadWorkerMessages }}</span>
            <span class="strip-s">{{ __('From field workers') }}</span>
        </a>
    </div>

    <div class="grid g-2-1">
        <div class="stack">
            <section class="card">
                <div class="card-h">
                    <h2 class="card-t">{{ __('Invitations requiring response') }}</h2>
                    <span class="sp"></span>
                    <a href="{{ route('supplier.invitations.index') }}" class="link sm">{{ __('View all') }}</a>
                </div>
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr>
                            <th>{{ __('Request') }}</th>
                            <th>{{ __('Buyer') }}</th>
                            <th>{{ __('Deadline') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse($recentInvitations as $invitation)
                            <tr>
                                <td><span class="mono sm muted">#{{ $invitation->request_id }}</span>
                                    <div class="t1">{{ $invitation->request?->title ?? __('Request unavailable') }}</div></td>
                                <td class="sm">{{ $invitation->request?->buyer?->name ?? '—' }}</td>
                                <td class="mono sm">{{ $invitation->request?->deadline?->format('d M Y') ?? '—' }}</td>
                                <td><a class="btn btn-sm" href="{{ route('supplier.invitations.index') }}">{{ __('Review') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="empty"><h3>{{ __('No invitations need a response') }}</h3><p>{{ __('New requests will appear here when you are invited.') }}</p></div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Ready for quotation') }}</h2><span class="card-s">{{ __('Field assessment completed') }}</span></div>
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>{{ __('Request') }}</th><th>{{ __('Buyer') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @forelse($rfqsWithFieldAssessment as $rfq)
                            <tr>
                                <td><span class="mono sm muted">#{{ $rfq->id }}</span><div class="t1">{{ $rfq->title }}</div></td>
                                <td>{{ $rfq->buyer?->name ?? '—' }}</td>
                                <td class="mono sm">{{ $rfq->deadline?->format('d M Y') ?? '—' }}</td>
                                <td><span class="badge b-ok">{{ __('Assessed') }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="empty">{{ __('No assessed RFQs are awaiting a quotation.') }}</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Your procurement activity') }}</h2></div>
                <div class="card-b stack">
                    <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Accepted quotations') }}</span><b class="mono">{{ $wonQuotes }}</b></div>
                    <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Active field workers') }}</span><b class="mono">{{ $activeWorkers }} / {{ $totalWorkers }}</b></div>
                    <div class="row" style="justify-content:space-between"><span class="muted">{{ __('Worker assignments') }}</span><b class="mono">{{ $workerAssignments }}</b></div>
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Quick actions') }}</h2></div>
                <div class="card-b stack">
                    <a class="btn btn-p" href="{{ route('supplier.invitations.index') }}">{{ __('Review new RFQs') }}</a>
                    <a class="btn" href="{{ route('supplier.quotes.index') }}">{{ __('My quotations') }}</a>
                    <a class="btn" href="{{ route('supplier.workers.index') }}">{{ __('Field workers') }}</a>
                </div>
            </section>
            <div class="note">{{ __('Only your company’s invitations and quotations are displayed in this workspace.') }}</div>
        </aside>
    </div>
</div>
