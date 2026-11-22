<div class="stack">
    <header class="ph"><div>
        <div class="row sm muted"><a class="link" href="{{ route('supplier.field.rfq.index') }}" wire:navigate>{{ __('Assignments') }}</a><span>/</span><span class="mono">#{{ $request->id }}</span></div>
        <h1 class="ph-t">{{ $request->title }}</h1>
        <p class="ph-d">{{ $request->delivery_location ?: __('Site not specified') }} · {{ __('Due') }}: {{ $request->deadline?->format('d M Y') ?? '—' }}</p>
    </div><div class="ph-a"><span class="badge {{ $assignment->status === 'done' ? 'b-ok' : 'b-info' }}">{{ ucfirst(str_replace('_',' ',$assignment->status)) }}</span></div></header>

    <div class="facts">
        <div class="fact"><span>{{ __('Reference') }}</span><b>RFQ #{{ $request->id }}</b></div>
        <div class="fact"><span>{{ __('Site contact') }}</span><b>{{ $request->buyer?->name ?? '—' }}</b></div>
        <div class="fact"><span>{{ __('Checklist') }}</span><b class="mono">{{ $checks->whereNotNull('result')->count() }}/{{ $checks->count() }}</b></div>
        <div class="fact"><span>{{ __('Status') }}</span><b>{{ ucfirst(str_replace('_',' ',$assignment->status)) }}</b></div>
    </div>

    <div class="grid g-2-1">
        <div class="stack">
            <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Assessment checklist') }}</h2>
                <span class="card-s">{{ $checks->whereNotNull('result')->count() }} {{ __('answered') }}</span></div>
                <div class="card-b stack">
                    @if($assignment->status === 'pending')
                        <div class="note">{{ __('Start your assessment to create the inspection checklist.') }}</div>
                        <button class="btn btn-p" wire:click="start" type="button">{{ __('Start assessment') }}</button>
                    @else
                        @foreach($checks as $check)
                            <div class="source-checklist-row" wire:key="field-check-{{ $check->id }}">
                                <span class="t1">{{ $check->label }}</span>
                                @if($assignment->status === 'done')
                                    <span class="badge {{ $check->result === 'pass' ? 'b-ok' : 'b-bad' }}">{{ $check->result === 'pass' ? __('Passed') : __('Failed') }}</span>
                                @else
                                    <div class="row">
                                        <button class="btn btn-sm {{ $check->result === 'pass' ? 'btn-p' : '' }}"
                                            wire:click="setResult({{ $check->id }}, 'pass')" type="button"
                                            aria-pressed="{{ $check->result === 'pass' ? 'true' : 'false' }}">{{ __('Pass') }}</button>
                                        <button class="btn btn-sm {{ $check->result === 'fail' ? 'btn-d' : '' }}"
                                            wire:click="setResult({{ $check->id }}, 'fail')" type="button"
                                            aria-pressed="{{ $check->result === 'fail' ? 'true' : 'false' }}">{{ __('Fail') }}</button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                        @if($assignment->status !== 'done')
                            @error('checklist')<div class="note bad" role="alert">{{ $message }}</div>@enderror
                            <button class="btn btn-p" wire:click="submit" type="button"
                                wire:confirm="{{ __('Submit the assessment? You cannot edit it afterwards.') }}"
                                wire:loading.attr="disabled">{{ __('Submit assessment') }}</button>
                        @else
                            <div class="note">{{ __('Submitted and read-only. The buyer can review the recorded field-assessment result.') }}</div>
                        @endif
                    @endif
                </div>
            </section>
            <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Notes') }}</h2></div>
                <div class="card-b stack">
                    @foreach($notes as $note)
                        <div class="source-note" wire:key="field-note-{{ $note->id }}">
                            <p>{{ $note->body }}</p><time class="sm muted">{{ \Carbon\Carbon::parse($note->created_at)->format('d M Y H:i') }}</time>
                        </div>
                    @endforeach
                    @if($assignment->status !== 'done')
                        <form wire:submit="addNote" class="stack">
                            <label for="field-new-note">{{ __('Add a note') }}</label>
                            <textarea id="field-new-note" class="textarea" rows="3" wire:model="newNote"></textarea>
                            @error('newNote')<span class="err">{{ $message }}</span>@enderror
                            <button class="btn btn-sm" type="submit">{{ __('Add note') }}</button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
        <aside class="stack">
            <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Photos and evidence') }}</h2></div>
                <div class="card-b stack">
                    @forelse($files as $file)
                        <button type="button" class="btn" wire:click="downloadEvidence({{ $file->id }})" wire:key="field-file-{{ $file->id }}">
                            <x-source.icon name="camera" :size="15"/>{{ $file->original_name }}</button>
                    @empty<div class="empty">{{ __('No photos uploaded') }}</div>@endforelse
                    @if($assignment->status !== 'done')
                        <form wire:submit="uploadEvidence" class="stack">
                            <label for="field-evidence">{{ __('Add photo') }}</label>
                            <input id="field-evidence" class="input" type="file" wire:model="evidence" accept="image/jpeg,image/png,image/webp" required/>
                            @error('evidence')<span class="err">{{ $message }}</span>@enderror
                            <button type="submit" class="btn btn-p" wire:loading.attr="disabled">{{ __('Upload photo') }}</button>
                        </form>
                    @endif
                </div>
            </section>
            <section class="card"><div class="card-h"><h2 class="card-t">{{ __('Task details') }}</h2></div>
                <div class="card-b"><dl class="kv">
                    <dt>{{ __('Buyer') }}</dt><dd>{{ $request->buyer?->name ?? '—' }}</dd>
                    <dt>{{ __('Location') }}</dt><dd>{{ $request->delivery_location ?: '—' }}</dd>
                    <dt>{{ __('Request') }}</dt><dd>{{ $request->description ?: '—' }}</dd>
                    <dt>{{ __('Instructions') }}</dt><dd>{{ $request->special_instructions ?: '—' }}</dd>
                </dl></div>
            </section>
        </aside>
    </div>
</div>