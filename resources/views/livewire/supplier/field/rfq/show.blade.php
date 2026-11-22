<div class="stack">
    <header class="ph">
        <div>
            <div class="row sm muted"><a class="link" href="{{ route('supplier.field.rfq.index') }}">{{ __('Assigned RFQs') }}</a><span>/</span><span class="mono">#{{ $request->id }}</span></div>
            <h1 class="ph-t">{{ $request->title }}</h1>
            <p class="ph-d">{{ __('Buyer') }}: {{ $request->buyer?->name ?? '—' }} · {{ __('Deadline') }}: {{ $request->deadline?->format('d M Y') ?? '—' }}</p>
        </div>
        <div class="ph-a"><span class="badge {{ $assignment->status === 'done' ? 'b-ok' : ($assignment->status === 'in_progress' ? 'b-info' : 'b-warn') }}">{{ ucfirst(str_replace('_',' ',$assignment->status)) }}</span></div>
    </header>
    <div class="facts">
        <div class="fact"><span>{{ __('Assignment') }}</span><b>{{ ucfirst(str_replace('_',' ',$assignment->status)) }}</b></div>
        <div class="fact"><span>{{ __('Request status') }}</span><b>{{ ucfirst($request->status) }}</b></div>
        <div class="fact"><span>{{ __('Deadline') }}</span><b>{{ $request->deadline?->format('d M Y') ?? '—' }}</b></div>
        <div class="fact"><span>{{ __('Items') }}</span><b class="mono">{{ $request->items->count() }}</b></div>
    </div>
    <div class="grid g-2-1">
        <div class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Inspection scope') }}</h2></div>
                <div class="card-b stack">
                    <p>{{ $request->description ?: __('No description was supplied.') }}</p>
                    @if($request->special_instructions)
                        <div class="note warn"><strong>{{ __('Special instructions') }}:</strong> {{ $request->special_instructions }}</div>
                    @endif
                    @if($request->delivery_location)<p class="muted sm">{{ __('Location') }}: {{ $request->delivery_location }}</p>@endif
                </div>
            </section>
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('Requested items') }}</h2></div>
                <div class="tbl-wrap"><table class="tbl">
                    <thead><tr><th>{{ __('Product') }}</th><th class="num">{{ __('Quantity') }}</th><th>{{ __('Specifications') }}</th></tr></thead>
                    <tbody>
                    @forelse($request->items as $item)
                        <tr><td class="t1">{{ $item->product_name ?: '—' }}</td><td class="num mono">{{ $item->quantity }}</td><td class="sm muted">{{ $item->specifications ?: '—' }}</td></tr>
                    @empty<tr><td colspan="3"><div class="empty">{{ __('No listed items.') }}</div></td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </section>
        </div>
        <aside class="stack">
            <section class="card">
                <div class="card-h"><h2 class="card-t">{{ __('My progress') }}</h2></div>
                <div class="card-b stack">
                    <p class="muted sm">{{ __('Update the assignment as work progresses. Each change is saved to the real assignment.') }}</p>
                    @foreach(['pending' => __('Pending'), 'in_progress' => __('In progress'), 'done' => __('Done')] as $status => $label)
                        <button type="button" class="btn {{ $assignment->status === $status ? 'btn-p' : '' }}" wire:click="updateStatus('{{ $status }}')">{{ $label }}</button>
                    @endforeach
                    @if($assignment->notes)<p class="muted sm">{{ $assignment->notes }}</p>@endif
                </div>
            </section>
            @if($request->latestFieldAssessment)
                <section class="card">
                    <div class="card-h"><h2 class="card-t">{{ __('Existing field assessment') }}</h2></div>
                    <div class="card-b">
                        <dl class="kv">
                            <dt>{{ __('Status') }}</dt><dd>{{ ucfirst($request->latestFieldAssessment->status) }}</dd>
                            <dt>{{ __('Technical feasibility') }}</dt><dd>{{ $request->latestFieldAssessment->technical_feasibility ?: '—' }}</dd>
                            <dt>{{ __('Field notes') }}</dt><dd>{{ $request->latestFieldAssessment->notes ?: '—' }}</dd>
                        </dl>
                    </div>
                </section>
            @endif
            <div class="note">{{ __('Inspection checklists and photo uploads require additional backend storage and validation; they are not active yet.') }}</div>
        </aside>
    </div>
</div>