@php use App\Models\FieldAssessment; $bv = ['submitted' => 'info', 'approved' => 'ok', 'rejected' => 'bad', 'in_progress' => 'warn']; $quick = ['' => ['All', $counts->sum()]]; foreach (FieldAssessment::STATUSES as $k => $l) { if ($counts[$k] ?? 0) { $quick[$k] = [$l, $counts[$k]]; } } @endphp
<div>
    <x-ph title="Field assessments" :desc="$sourcing ? 'Site checks you asked suppliers to carry out. Approve them or return them for correction.' : 'Site checks the buyer asked for. Answer the checklist, write the findings and submit.'" :crumbs="[['Field assessments']]" />
    <x-dt :lw="$this" :rows="$rows" id="fa" search="Search reference, title or supplier" label="Search field assessments" noun="assessments" :all="$all" :quick="$quick">
        <x-slot:empty><x-empty icon="camera" title="No field assessments yet" :text="$sourcing ? 'Request one from a request for quotation or from a supplier profile.' : 'When a buyer asks for a site check, it appears here.'" /></x-slot:empty>
        <x-slot:head><x-th col="number" cls="hide-sm">Reference</x-th><x-th col="title">Assessment</x-th><th class="hide-md">Request</th><th class="hide-md">Assigned to</th><x-th col="status">Status</x-th><x-th col="due_on">Due</x-th></x-slot:head>
        @foreach ($rows as $a)
            <tr wire:key="fa-{{ $a->id }}" class="click" data-href="{{ route('assessments.show', $a) }}" tabindex="0"><td class="hide-sm"><span class="mono" style="white-space:nowrap">{{ $a->number }}</span></td><td class="tmain"><div class="t1">{{ $a->title }}</div><div class="t2">{{ $a->supplier->name }}</div></td>
                <td class="hide-md mono">{{ $a->rfq?->number ?? '-' }}</td><td class="hide-md">{{ $a->assignee?->name ?? 'Not assigned' }}</td><td><x-badge :status="$bv[$a->status] ?? 'draft'" :label="$a->statusLabel()" /></td><td style="white-space:nowrap">{{ $a->due_on?->format('j M Y') ?? '-' }}</td></tr>
        @endforeach
    </x-dt>
</div>
