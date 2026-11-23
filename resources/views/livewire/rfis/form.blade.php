<div>
    <x-ph :title="$number ? 'Edit ' . $number : 'New information request'" desc="Write the questions, choose who to ask and set a deadline." :crumbs="[['Information requests', route('rfis.index')], [$number ?? 'New']]">
        <a class="btn btn-g" href="{{ $number ? route('rfis.show', $number) : route('rfis.index') }}" wire:navigate>Discard</a>
    </x-ph>
    <div class="grid g-2-1">
        <div class="stack">
            <x-card title="Request"><div class="fgrid">
                <x-field label="Title" for="ri-t" :full="true"><input id="ri-t" class="input" wire:model="f.title" placeholder="e.g. Market sounding - precast concrete elements" autofocus></x-field>
                <x-field label="Answer by" for="ri-d"><input id="ri-d" type="datetime-local" class="input" min="{{ now()->format('Y-m-d') }}" wire:model="f.deadline_at"></x-field>
                <x-field label="Background" for="ri-b" :full="true" :opt="true"><textarea id="ri-b" class="textarea" rows="3" wire:model="f.description"></textarea></x-field>
            </div></x-card>
            <x-card title="Questions" sub="Every supplier answers all of them">
                <x-slot:act><button type="button" class="btn btn-sm" wire:click="addQuestion"><x-icon name="plus" :size="14" />Add question</button></x-slot:act>
                <div class="stack" style="gap:10px">@foreach ($questions as $i => $q)<div class="row" wire:key="q-{{ $i }}" style="align-items:flex-start"><span class="mono muted" style="width:22px;padding-top:9px">{{ $i + 1 }}</span><input class="input grow" wire:model="questions.{{ $i }}" aria-label="Question {{ $i + 1 }}" placeholder="e.g. What is your usual lead time for 200 units?"><button type="button" class="icon-btn" wire:click="removeQuestion({{ $i }})" aria-label="Remove question {{ $i + 1 }}"><x-icon name="trash" :size="15" /></button></div>@endforeach</div>
            </x-card>
        </div>
        <x-card title="Suppliers to ask" :sub="count($suppliers) . ' chosen'" :flush="true">
            @if ($list->isEmpty())<x-empty icon="building" title="No active suppliers" text="Approve a supplier first." />
            @else<ul class="list" style="max-height:520px;overflow:auto">@foreach ($list as $s)<li wire:key="rs-{{ $s->id }}"><label class="row grow" style="gap:12px;cursor:pointer"><input type="checkbox" class="chk" value="{{ $s->id }}" wire:model="suppliers"><div class="grow"><div class="t1">{{ $s->name }}</div><div class="muted sm">{{ $s->category?->name ?? 'No category' }}{{ $s->city ? ' · ' . $s->city : '' }}</div></div></label></li>@endforeach</ul>@endif
        </x-card>
    </div>
    <div class="row" style="margin-top:18px"><span class="sp"></span><button type="button" class="btn" wire:click="save(false)" wire:loading.attr="disabled">Save draft</button><button type="button" class="btn btn-p" wire:click="save(true)" wire:loading.attr="disabled"><x-icon name="send" />Send to suppliers</button></div>
</div>
