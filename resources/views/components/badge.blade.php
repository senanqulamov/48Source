@props(['status', 'label' => null])
@php
    $variants = ['open' => 'info', 'closed' => 'warn', 'awarded' => 'ok', 'cancelled' => 'bad', 'issued' => 'info', 'acknowledged' => 'info', 'in_transit' => 'warn', 'delivered' => 'ok', 'invoiced' => 'ok', 'active' => 'ok', 'pending' => 'warn', 'blocked' => 'bad', 'invited' => 'info', 'disabled' => 'bad', 'submitted' => 'ok', 'won' => 'ok', 'lost' => 'bad', 'accepted' => 'ok', 'declined' => 'bad', 'ok' => 'ok', 'warn' => 'warn', 'bad' => 'bad', 'info' => 'info'];
    $v = $variants[$status] ?? null;
@endphp
<span {{ $attributes->class(['badge', $v ? 'b-' . $v : '']) }}>{{ $label ?? \Illuminate\Support\Str::of($status)->replace('_', ' ')->ucfirst() }}</span>
