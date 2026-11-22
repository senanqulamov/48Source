<div>
    <section class="card"><div class="card-b source-legacy-content">
        <div class="card-h">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">{{ __('Feature Flags') }}</h3>
            </div>
        </div>

        <div class="divide-y divide-slate-800/50">
            @foreach($flags as $flag)
                <div class="flex items-center justify-between p-3">
                    <div>
                        <div class="font-semibold text-sm">{{ $flag['key'] }}</div>
                        <div class="text-xs text-slate-400">{{ $flag['description'] }}</div>
                    </div>
                    <x-toggle wire:click="toggle({{ $flag['id'] }})" :checked="$flag['enabled']" />
                </div>
            @endforeach
            @if(empty($flags))
                <div class="p-8 text-center text-slate-400">{{ __('No flags found') }}</div>
            @endif
        </div>
    </div></section>
</div>
