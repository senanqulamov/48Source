@props(['col', 'sort' => null, 'dir' => null, 'num' => false, 'cls' => ''])
@aware(['lw' => null])
@php
    $sort ??= $lw?->sort; $dir ??= $lw?->dir; $on = $sort === $col;
    $label = strtolower(trim(strip_tags($slot)));
@endphp
<th class="{{ $cls }} {{ $num ? 'num' : '' }}" data-c="{{ $col }}" aria-sort="{{ $on ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <button type="button" class="{{ $on ? 'on' : '' }}" wire:click="sortBy('{{ $col }}')" title="Sort by {{ $label }}">{{ $slot }}<span class="sorti {{ $on ? $dir : '' }}"><x-icon :name="$on ? 'up' : 'sort'" :size="12" /></span></button>
</th>
