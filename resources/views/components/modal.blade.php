@props(['title', 'wide' => false])
<div class="scrim" x-data="ov" x-on:click.self="bye()" x-on:keydown.escape.window="bye()">
    <div @class(['modal', 'wide' => $wide]) role="dialog" aria-modal="true" aria-label="{{ $title }}" x-trap.noscroll="true" wire:loading.attr="aria-busy" aria-busy="false">
        <div class="ov-h"><h2>{{ $title }}</h2><span class="sp"></span><button type="button" class="icon-btn" x-on:click="bye()" aria-label="Close"><x-icon name="x" /></button></div>
        <div class="ov-b">{{ $slot }}<div class="ov-loading" wire:loading.delay.flex aria-hidden="true"><span class="sk"></span><span class="sk"></span><span class="sk"></span></div></div>
        @isset($foot)<div class="ov-f">{{ $foot }}</div>@endisset
    </div>
</div>
