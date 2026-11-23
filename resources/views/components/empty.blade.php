@props(['icon' => 'inbox', 'title', 'text' => ''])
<div class="empty"><div class="ei"><x-icon :name="$icon" :size="22" /></div><h3>{{ $title }}</h3><p>{{ $text }}</p>{{ $slot }}</div>
