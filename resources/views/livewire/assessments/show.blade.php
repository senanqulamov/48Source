@php $bv = ['submitted' => 'info', 'approved' => 'ok', 'rejected' => 'bad', 'in_progress' => 'warn']; $at = ['assigned' => 0, 'in_progress' => 1, 'rejected' => 1, 'submitted' => 2, 'approved' => 3][$fa->status]; @endphp
<div>
    <x-ph :title="$fa->title" :crumbs="[['Field assessments', route('assessments.index')], [$fa->number]]" :recent="$fa->number" :recent-sub="$fa->title">
        <x-slot:pre><div class="row wrap" style="margin-bottom:4px"><span class="mono muted">{{ $fa->number }}</span><x-badge :status="$bv[$fa->status] ?? 'draft'" :label="$fa->statusLabel()" /><span class="tag">{{ $fa->supplier->name }}</span></div></x-slot:pre>
        @if ($canFill)<button type="button" class="btn" wire:click="save" wire:loading.attr="disabled">Save progress</button><button type="button" class="btn btn-p" wire:click="submit" wire:loading.attr="disabled"><x-icon name="send" />Submit</button>@endif
        @if ($sourcing && $fa->status === 'submitted')@perm('field', 'A')<button type="button" class="btn btn-d" wire:click="open('return')">Return for correction</button><button type="button" class="btn btn-p" wire:click="decide(true)" wire:confirm="Approve this assessment?"><x-icon name="check" />Approve</button>@endperm @endif
    </x-ph>
    <x-facts :items="[['Supplier', $fa->supplier->name], ['Request', $fa->rfq?->number ?? 'Not linked', '', true], ['Asked by', $fa->requester->name], ['Assigned to', $fa->assignee?->name ?? 'Anyone at the supplier'], ['Due', $fa->due_on?->format('j M Y') ?? 'Not set']]" />
    <x-life label="Assessment progress" :steps="['Assigned', 'In progress', 'Submitted', 'Approved']" :at="$at" :final="$fa->status === 'approved'">
        <x-slot:next>
            @switch($fa->status)
                @case('assigned') {{ $sourcing ? 'Waiting for the supplier to start.' : 'Next step: answer the checklist below.' }} @break
                @case('in_progress') {{ $sourcing ? 'The supplier is working on it.' : 'Finish the checklist and the findings, then submit.' }} @break
                @case('rejected') Returned for correction: {{ $fa->decision_note }} @break
                @case('submitted') {{ $sourcing ? 'Next step: approve it or return it for correction.' : 'Waiting for the buyer to review it.' }} @break
                @case('approved') Approved by {{ $fa->decider?->name }} on {{ $fa->decided_at->format('j M Y') }}. @break
            @endswitch
        </x-slot:next>
    </x-life>
    <div class="stack">
        <x-card title="Checklist" :sub="count($fa->checklist) . ' points'" :flush="true">
            <ul class="list">
                @foreach ($fa->checklist as $i => $point)
                    @php $a = $fa->answers[$i] ?? null; @endphp
                    <li wire:key="cl-{{ $i }}" style="align-items:flex-start;flex-wrap:wrap"><span class="mono muted" style="width:22px">{{ $i + 1 }}</span><div class="grow" style="min-width:220px"><div class="t1">{{ $point }}</div>
                        @if ($canFill)<input class="input" style="margin-top:8px" wire:model="ans.{{ $i }}.note" placeholder="Note (needed when the answer is no)" aria-label="Note for point {{ $i + 1 }}">
                        @elseif (! empty($a['note']))<div class="muted sm">{{ $a['note'] }}</div>@endif</div>
                        @if ($canFill)<div class="seg" role="group" aria-label="Answer for point {{ $i + 1 }}"><button type="button" class="{{ $ans[$i]['ok'] === 'yes' ? 'on' : '' }}" wire:click="$set('ans.{{ $i }}.ok', 'yes')">Yes</button><button type="button" class="{{ $ans[$i]['ok'] === 'no' ? 'on' : '' }}" wire:click="$set('ans.{{ $i }}.ok', 'no')">No</button></div>
                        @else<x-badge :status="($a['ok'] ?? null) === null ? 'draft' : ($a['ok'] ? 'ok' : 'bad')" :label="($a['ok'] ?? null) === null ? 'Not answered' : ($a['ok'] ? 'Yes' : 'No')" />@endif</li>
                @endforeach
            </ul>
        </x-card>
        <x-card title="Findings">
            @if ($canFill)<x-field label="What was found on site" for="fa-find" help="At least 10 characters."><textarea id="fa-find" class="textarea" rows="4" wire:model="findings"></textarea></x-field>
            @else<p style="max-width:75ch;white-space:pre-line">{{ $fa->findings ?: 'Nothing written yet.' }}</p>@if ($fa->submitted_at)<p class="muted sm" style="margin-top:8px">Submitted {{ $fa->submitted_at->format('j M Y, H:i') }}</p>@endif @endif
        </x-card>
    </div>
    @if ($modal === 'return')
        <x-modal title="Return for correction?"><x-field label="What has to be corrected" for="fa-rn"><textarea id="fa-rn" class="textarea" rows="3" wire:model="note" autofocus></textarea></x-field>
            <x-slot:foot><button type="button" class="btn" x-on:click="bye()">Cancel</button><button type="button" class="btn btn-d solid" wire:click="decide(false)">Return to supplier</button></x-slot:foot></x-modal>
    @endif
</div>
