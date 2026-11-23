@php use App\Support\Money; $u = auth()->user(); @endphp
<div>
    <x-ph :title="'Welcome, ' . \Illuminate\Support\Str::before($u->name, ' ')" :desc="$store ? $store->name . ' · ' . $store->market->name : __($u->roleLabel()) . ' workspace'" />
    @if (in_array($u->role, ['seller', 'market_worker']) && ! $store)
        <x-card><x-empty icon="store" title="Your account is not linked to a store yet" text="Ask an administrator to add you to your store under Markets & stores. Notifications and settings already work."><a class="btn btn-p" href="{{ route('notifications.index') }}" wire:navigate>Open notifications</a></x-empty></x-card>
    @elseif ($store)
        <x-shortcuts :items="$shortcuts" />
        <x-strip :items="$strip" />
        <div class="grid g-2-1">
            <x-card title="Orders needing action" :flush="true">
                <x-slot:act><a class="btn btn-sm btn-g" href="{{ route('market.orders.index') }}" wire:navigate>All sales</a></x-slot:act>
                @if ($orders->isEmpty())<x-empty icon="check" title="Nothing waiting" text="New orders and open tasks appear here." />
                @else<ul class="list">@foreach ($orders as $o)<li class="click" style="cursor:pointer" data-href="{{ route('market.orders.show', $o) }}" tabindex="0"><div class="grow"><div class="t1">{{ $o->supplier?->name ?? $o->buyer->name }}</div><div class="muted sm"><span class="mono">{{ $o->number }}</span> · {{ $o->created_at->diffForHumans() }}</div></div><b class="mono">{{ Money::format($o->total_cents, $o->currency) }}</b><x-badge :status="['placed' => 'info', 'confirmed' => 'warn', 'ready' => 'warn'][$o->status] ?? 'ok'" :label="$o->statusLabel()" /><x-icon name="right" /></li>@endforeach</ul>@endif
            </x-card>
            <x-card title="Low stock" sub="Under the smallest order" :flush="true">
                @if ($low->isEmpty())<div class="card-b faint">Every product on sale has stock.</div>
                @else<ul class="list">@foreach ($low as $x)<li><div class="grow"><div class="t1">{{ $x->product->name }}</div><div class="muted sm mono">{{ $x->product->sku }}</div></div><span class="mono" style="color:var(--bad)">{{ Money::qty($x->stock_milli) }} {{ $x->product->unit }}</span></li>@endforeach</ul>@endif
            </x-card>
        </div>
    @else
        <x-shortcuts :items="[[route('assessments.index'), 'camera', 'Field assessments', $todo . ' waiting for you'], [route('notifications.index'), 'bell', 'Notifications', 'Everything the system told you'], [route('settings'), 'sliders', 'Settings', 'Profile, password and language']]" />
        <x-card title="Your field assessments" :flush="true">
            @if ($assessments->isEmpty())<x-empty icon="camera" title="No field assessments yet" text="When a buyer asks for a site check, it appears here." />
            @else<ul class="list">@foreach ($assessments as $a)<li class="click" style="cursor:pointer" data-href="{{ route('assessments.show', $a) }}" tabindex="0"><div class="grow"><div class="t1">{{ $a->title }}</div><div class="muted sm"><span class="mono">{{ $a->number }}</span>{{ $a->due_on ? ' · due ' . $a->due_on->format('j M Y') : '' }}</div></div><x-badge :status="['submitted' => 'info', 'approved' => 'ok', 'rejected' => 'bad', 'in_progress' => 'warn'][$a->status] ?? 'draft'" :label="$a->statusLabel()" /><x-icon name="right" /></li>@endforeach</ul>@endif
        </x-card>
    @endif
</div>
